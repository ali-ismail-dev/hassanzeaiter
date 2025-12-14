<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CategoryField;
use App\Models\CategoryFieldOption;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CategorySyncService
{
    public function __construct(
        private OlxApiService $olxApiService
    ) {}

    /**
     * Sync all categories and their fields from OLX API.
     */
    public function syncAll(bool $forceRefresh = false): array
    {
        $stats = [
            'categories' => 0,
            'fields' => 0,
            'options' => 0,
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            // Step 1: Sync categories
            $categoriesData = $this->olxApiService->fetchCategories($forceRefresh);
            $stats['categories'] = $this->syncCategories($categoriesData);

            // Step 2: Get all category external IDs
            $categoryExternalIds = array_column($categoriesData, 'id');

            // Step 3: Sync category fields (batch request)
            if (!empty($categoryExternalIds)) {
                $fieldsData = $this->olxApiService->fetchCategoryFields($categoryExternalIds, $forceRefresh);
                $fieldStats = $this->syncCategoryFields($fieldsData);
                $stats['fields'] = $fieldStats['fields'];
                $stats['options'] = $fieldStats['options'];
            }

            DB::commit();
            Log::info('Successfully synced OLX data', $stats);

            return $stats;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to sync OLX data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $stats['errors'][] = $e->getMessage();
            throw $e;
        }
    }

    /**
     * Sync categories from API data.
     */
    private function syncCategories(array $categoriesData): int
    {
        $count = 0;

        foreach ($categoriesData as $categoryData) {
            $category = Category::updateOrCreate(
                ['external_id' => (string) $categoryData['id']],
                [
                    'name' => $categoryData['name'] ?? 'Unknown',
                    'slug' => isset($categoryData['slug']) 
                        ? $categoryData['slug'] 
                        : Str::slug($categoryData['name'] ?? 'unknown'),
                    'description' => $categoryData['description'] ?? null,
                    'parent_id' => $this->findParentId($categoryData),
                    'order' => $categoryData['order'] ?? 0,
                    'metadata' => $this->extractCategoryMetadata($categoryData),
                ]
            );

            $count++;
            Log::debug("Synced category: {$category->name} (ID: {$category->external_id})");
        }

        return $count;
    }

    /**
     * Sync category fields and their options.
     * API returns structure keyed by categoryId with "flatFields", "childrenFields", and "parentFieldLookup"
     */
    private function syncCategoryFields(array $fieldsDataByCategory): array
    {
        $fieldCount = 0;
        $optionCount = 0;

        foreach ($fieldsDataByCategory as $categoryExternalId => $categoryData) {
            // Skip common category fields and special keys
            if ($categoryExternalId === 'common_category_fields' || !is_numeric($categoryExternalId)) {
                continue;
            }

            $category = Category::where('external_id', (string) $categoryExternalId)->first();

            if (!$category) {
                Log::warning("Category not found for external_id: {$categoryExternalId}");
                continue;
            }

            // Extract flatFields which contains the actual field definitions
            if (!is_array($categoryData) || !isset($categoryData['flatFields']) || !is_array($categoryData['flatFields'])) {
                Log::warning("No flatFields found for category: {$categoryExternalId}");
                continue;
            }

            $flatFields = $categoryData['flatFields'];

            // Process each field
            foreach ($flatFields as $fieldKey => $fieldData) {
                // Check if this is a field object with metadata
                if (is_array($fieldData) && isset($fieldData['attribute'])) {
                    // This is a field object with properties
                    $field = $this->syncCategoryField($category, $fieldData);
                    $fieldCount++;

                    if (isset($fieldData['choices']) && is_array($fieldData['choices'])) {
                        $optionCount += $this->syncFieldOptions($field, $fieldData['choices']);
                    }
                }
            }
        }

        return [
            'fields' => $fieldCount,
            'options' => $optionCount,
        ];
    }

    /**
     * Sync a single category field.
     */
    private function syncCategoryField(Category $category, array $fieldData): CategoryField
    {
        // API field structure uses 'attribute' as the identifier and 'filterType' as field type
        $fieldName = $fieldData['attribute'] ?? $fieldData['name'] ?? 'unknown_field';
        $fieldType = $this->olxApiService->mapFieldType($fieldData['filterType'] ?? $fieldData['type'] ?? 'text');

        return CategoryField::updateOrCreate(
            [
                'category_id' => $category->id,
                'external_id' => (string) ($fieldData['id'] ?? $fieldName),
            ],
            [
                'name' => $fieldName,
                'label' => $fieldData['name'] ?? $fieldName,
                'field_type' => $fieldType,
                'is_required' => $fieldData['isMandatory'] ?? $fieldData['required'] ?? false,
                'is_searchable' => $fieldData['searchable'] ?? false,
                'order' => $fieldData['displayPriority'] ?? $fieldData['order'] ?? 0,
                'validation_rules' => $this->extractValidationRules($fieldData),
                'placeholder' => $fieldData['placeholder'] ?? null,
                'help_text' => $fieldData['help_text'] ?? $fieldData['hint'] ?? null,
                'metadata' => $this->extractFieldMetadata($fieldData),
            ]
        );
    }

    /**
     * Sync field options for select/radio/checkbox fields.
     */
    private function syncFieldOptions(CategoryField $field, array $choicesData): int
    {
        $count = 0;

        // Delete existing options that are no longer in the API
        $newOptionExternalIds = array_filter(array_column($choicesData, 'id'));
        if (!empty($newOptionExternalIds)) {
            $field->options()
                ->whereNotIn('external_id', $newOptionExternalIds)
                ->delete();
        }

        foreach ($choicesData as $choiceData) {
            CategoryFieldOption::updateOrCreate(
                [
                    'category_field_id' => $field->id,
                    'external_id' => (string) ($choiceData['id'] ?? $choiceData['value']),
                ],
                [
                    'value' => $choiceData['value'] ?? $choiceData['label'],
                    'label' => $choiceData['label'] ?? $choiceData['value'],
                    'order' => $choiceData['order'] ?? $count,
                    'is_default' => $choiceData['default'] ?? false,
                    'metadata' => $this->extractOptionMetadata($choiceData),
                ]
            );

            $count++;
        }

        return $count;
    }

    /**
     * Find parent category ID from category data.
     */
    private function findParentId(array $categoryData): ?int
    {
        if (!isset($categoryData['parent_id']) || !$categoryData['parent_id']) {
            return null;
        }

        $parent = Category::where('external_id', (string) $categoryData['parent_id'])->first();
        return $parent?->id;
    }

    /**
     * Extract validation rules from field data.
     */
    private function extractValidationRules(array $fieldData): ?string
    {
        $rules = [];

        if (isset($fieldData['min'])) {
            $rules[] = "min:{$fieldData['min']}";
        }

        if (isset($fieldData['max'])) {
            $rules[] = "max:{$fieldData['max']}";
        }

        if (isset($fieldData['min_length'])) {
            $rules[] = "min:{$fieldData['min_length']}";
        }

        if (isset($fieldData['max_length'])) {
            $rules[] = "max:{$fieldData['max_length']}";
        }

        return !empty($rules) ? implode('|', $rules) : null;
    }

    /**
     * Extract metadata from category data.
     */
    private function extractCategoryMetadata(array $categoryData): array
    {
        return [
            'icon' => $categoryData['icon'] ?? null,
            'level' => $categoryData['level'] ?? null,
            'has_children' => $categoryData['has_children'] ?? false,
            'raw_data' => $categoryData,
        ];
    }

    /**
     * Extract metadata from field data.
     */
    private function extractFieldMetadata(array $fieldData): array
    {
        return [
            'unit' => $fieldData['unit'] ?? null,
            'suffix' => $fieldData['suffix'] ?? null,
            'prefix' => $fieldData['prefix'] ?? null,
            'raw_data' => $fieldData,
        ];
    }

    /**
     * Extract metadata from option data.
     */
    private function extractOptionMetadata(array $choiceData): array
    {
        return [
            'icon' => $choiceData['icon'] ?? null,
            'color' => $choiceData['color'] ?? null,
            'raw_data' => $choiceData,
        ];
    }
}