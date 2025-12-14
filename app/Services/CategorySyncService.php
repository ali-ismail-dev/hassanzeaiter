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

            // Step 2: Get all category external IDs (use externalID, not id)
            $categoryExternalIds = [];
            foreach ($categoriesData as $categoryData) {
                // Use externalID if available, fallback to id for backward compatibility
                $externalId = $categoryData['externalID'] ?? $categoryData['external_id'] ?? $categoryData['id'] ?? null;
                if ($externalId) {
                    $categoryExternalIds[] = (string) $externalId;
                }
            }
            $categoryExternalIds = array_unique($categoryExternalIds);

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
            // Use externalID if available, fallback to id for backward compatibility
            $externalId = $categoryData['externalID'] ?? $categoryData['external_id'] ?? $categoryData['id'] ?? null;
            
            if (!$externalId) {
                Log::warning('Category missing external identifier', ['category_data' => $categoryData]);
                continue;
            }
            
            $category = Category::updateOrCreate(
                ['external_id' => (string) $externalId],
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
     * API returns structure keyed by categoryId (internal id, not externalID) with "flatFields", "childrenFields", and "parentFieldLookup"
     * 
     * Note: The API returns fields keyed by internal 'id', but we store categories by 'external_id'.
     * We need to map the internal id back to the category using the metadata or by finding the category
     * that matches the internal id in its metadata.
     */
    private function syncCategoryFields(array $fieldsDataByCategory): array
    {
        $fieldCount = 0;
        $optionCount = 0;

        // Build a map of internal id -> category for lookup
        $internalIdToCategory = [];
        foreach (Category::all() as $cat) {
            $metadata = $cat->metadata ?? [];
            if (isset($metadata['raw_data']['id'])) {
                $internalId = (string) $metadata['raw_data']['id'];
                $internalIdToCategory[$internalId] = $cat;
            }
        }

        foreach ($fieldsDataByCategory as $categoryKey => $categoryData) {
            // Skip common category fields and special keys
            if ($categoryKey === 'common_category_fields' || !is_numeric($categoryKey)) {
                continue;
            }

            // The API returns fields keyed by internal 'id', not 'externalID'
            // Try to find category by internal id from metadata first
            $category = $internalIdToCategory[$categoryKey] ?? null;
            
            // Fallback: try to find by external_id (in case the key happens to match)
            if (!$category) {
                $category = Category::where('external_id', (string) $categoryKey)->first();
            }

            if (!$category) {
                Log::warning("Category not found for API key: {$categoryKey} (tried internal id and external_id)");
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
        // Check for parentID (from API) or parent_id (fallback)
        $parentExternalId = $categoryData['parentID'] ?? $categoryData['parent_id'] ?? null;
        
        if (!$parentExternalId) {
            return null;
        }

        // Look up parent by external_id
        $parent = Category::where('external_id', (string) $parentExternalId)->first();
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