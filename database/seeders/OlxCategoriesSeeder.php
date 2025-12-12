<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryField;
use App\Models\CategoryFieldOption;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class OlxCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting OLX categories seeding...');

        $localPath = database_path('seeders/data/olx_categories.json');
        $categoriesData = [];

        try {
            // Load JSON file if exists
            if (File::exists($localPath)) {
                $this->command->info("Loading categories from local file: {$localPath}");
                $json = json_decode(File::get($localPath), true);

                if (is_array($json)) {
                    $categoriesData = $json['data'] ?? $json;
                }
            } else {
                $this->command->warn('No local JSON file found. Using embedded sample categories.');
                // Minimal embedded fallback
                $categoriesData = [
                    [
                        'external_id' => 'sample-cars',
                        'name' => 'Cars (sample)',
                        'slug' => 'cars-sample',
                        'description' => 'Sample category for testing.',
                        'fields' => [
                            [
                                'external_id' => 'mileage',
                                'name' => 'Mileage',
                                'type' => 'number',
                                'required' => true,
                                'options' => []
                            ]
                        ]
                    ]
                ];
            }

            $categoriesSynced = 0;
            $fieldsSynced = 0;
            $optionsSynced = 0;

            foreach ($categoriesData as $cat) {
                $category = Category::updateOrCreate(
                    ['external_id' => $cat['external_id'] ?? $cat['id'] ?? 'sample-'.$categoriesSynced],
                    [
                        'name' => $cat['name'] ?? 'Unnamed',
                        'slug' => $cat['slug'] ?? strtolower(str_replace(' ', '-', $cat['name'] ?? 'category')),
                        'metadata' => $cat['description'] ?? null
                    ]
                );
                $categoriesSynced++;

                if (!empty($cat['fields'])) {
                    foreach ($cat['fields'] as $idx => $field) {
                        $categoryField = CategoryField::updateOrCreate(
                            [
                                'category_id' => $category->id,
                                'external_id' => $field['external_id'] ?? 'field-'.$idx
                            ],
                            [
                                'name' => $field['name'] ?? 'Unnamed Field',
                                'label' => $field['name'] ?? 'Unnamed Field',
                                'field_type' => $field['type'] ?? 'text',
                                'is_required' => $field['required'] ?? false,
                                'order' => $idx + 1
                            ]
                        );
                        $fieldsSynced++;

                        if (!empty($field['options']) && is_array($field['options'])) {
                            foreach ($field['options'] as $optIdx => $option) {
                                CategoryFieldOption::updateOrCreate(
                                    [
                                        'category_field_id' => $categoryField->id,
                                        'value' => $option
                                    ],
                                    [
                                        'label' => $option,
                                        'order' => $optIdx + 1
                                    ]
                                );
                                $optionsSynced++;
                            }
                        }
                    }
                }
            }

            $this->command->info('✅ Seeding completed successfully!');
            $this->command->table(
                ['Metric', 'Count'],
                [
                    ['Categories synced', $categoriesSynced],
                    ['Fields synced', $fieldsSynced],
                    ['Options synced', $optionsSynced],
                ]
            );

        } catch (\Throwable $e) {
            $this->command->error('❌ Seeder failed: ' . $e->getMessage());
            Log::error('OlxCategoriesSeeder failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            // Insert minimal fallback so app can still work
            $category = Category::updateOrCreate(
                ['external_id' => 'sample-cars'],
                ['name' => 'Cars (sample)', 'slug' => 'cars-sample', 'metadata' => []]
            );
            CategoryField::updateOrCreate(
                ['category_id' => $category->id, 'external_id' => 'mileage'],
                ['name' => 'Mileage', 'label' => 'Mileage', 'field_type' => 'number', 'is_required' => true, 'order' => 1]
            );
            $this->command->warn('✅ Minimal sample category inserted for testing.');
        }
    }
}
