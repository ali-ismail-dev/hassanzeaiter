<?php

namespace Database\Seeders;

use App\Models\Ad;
use App\Models\AdFieldValue;
use App\Models\Category;
use App\Models\CategoryField;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestAdSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::where('email', 'ali@gmail.com')->first();
        if (!$user) {
            return;
        }

        // Get available categories
        $categories = Category::whereIn('external_id', ['23', '61', '327'])->get();
        if ($categories->isEmpty()) {
            // Fallback: use first 3 categories
            $categories = Category::limit(3)->get();
        }

        // Ad 1: For category 23 (Cars)
        if ($category = $categories->first()) {
            $ad = Ad::create([
                'user_id' => $user->id,
                'category_id' => $category->id,
                'title' => 'Toyota Corolla 2020 - Great Condition',
                'description' => 'Well-maintained Toyota Corolla 2020 with full service history. Low mileage, all original parts.',
                'price' => 15000.00,
                'status' => 'active',
                'published_at' => now(),
                'expires_at' => now()->addDays(30),
            ]);

            // Add dynamic field values for this ad
            $this->seedAdFieldValues($ad, $category);
        }

        // Ad 2: For category 61 (Apartments & Villas)
        if ($category = $categories->get(1)) {
            $ad = Ad::create([
                'user_id' => $user->id,
                'category_id' => $category->id,
                'title' => 'Modern Apartment in Beirut - 2BR/2BA',
                'description' => 'Spacious 2-bedroom apartment with stunning city views. Recently renovated kitchen and modern amenities.',
                'price' => 1200.00,
                'status' => 'active',
                'published_at' => now(),
                'expires_at' => now()->addDays(30),
            ]);

            $this->seedAdFieldValues($ad, $category);
        }

        // Ad 3: For category 327 (Ball Sports)
        if ($category = $categories->get(2)) {
            $ad = Ad::create([
                'user_id' => $user->id,
                'category_id' => $category->id,
                'title' => 'Professional Soccer Ball - Used',
                'description' => 'High-quality leather soccer ball, barely used. Perfect for recreational play.',
                'price' => 45.00,
                'status' => 'active',
                'published_at' => now(),
                'expires_at' => now()->addDays(30),
            ]);

            $this->seedAdFieldValues($ad, $category);
        }

        // Ad 4: Another car listing (for category 23)
        if ($category = $categories->first()) {
            $ad = Ad::create([
                'user_id' => $user->id,
                'category_id' => $category->id,
                'title' => 'Honda Civic 2019 - Automatic',
                'description' => 'Reliable Honda Civic automatic transmission. Regular maintenance, clean interior.',
                'price' => 13500.00,
                'status' => 'active',
                'published_at' => now(),
                'expires_at' => now()->addDays(30),
            ]);

            $this->seedAdFieldValues($ad, $category);
        }

        // Ad 5: Another apartment (for category 61)
        if ($category = $categories->get(1)) {
            $ad = Ad::create([
                'user_id' => $user->id,
                'category_id' => $category->id,
                'title' => 'Luxury Villa in Achrafieh - 4BR',
                'description' => 'Beautiful villa with garden, swimming pool, and modern furnishings. Perfect for families.',
                'price' => 3500.00,
                'status' => 'active',
                'published_at' => now(),
                'expires_at' => now()->addDays(30),
            ]);

            $this->seedAdFieldValues($ad, $category);
        }
    }

    /**
     * Seed dynamic field values for an ad based on its category
     */
    private function seedAdFieldValues(Ad $ad, Category $category): void
    {
        $fields = CategoryField::where('category_id', $category->id)->get();

        foreach ($fields as $field) {
            $value = match ($field->field_type) {
                'text', 'textarea' => $this->generateTextValue($field),
                'integer', 'number' => $this->generateIntegerValue($field),
                'decimal' => $this->generateDecimalValue($field),
                'date' => $this->generateDateValue($field),
                'boolean' => rand(0, 1),
                'select', 'radio' => $this->generateSelectValue($field),
                default => null,
            };

            if ($value !== null) {
                AdFieldValue::create([
                    'ad_id' => $ad->id,
                    'category_field_id' => $field->id,
                    'value_text' => in_array($field->field_type, ['text', 'textarea']) ? $value : null,
                    'value_integer' => in_array($field->field_type, ['integer', 'number']) ? $value : null,
                    'value_decimal' => $field->field_type === 'decimal' ? $value : null,
                    'value_date' => $field->field_type === 'date' ? $value : null,
                    'value_boolean' => $field->field_type === 'boolean' ? $value : null,
                    'category_field_option_id' => in_array($field->field_type, ['select', 'radio']) ? $value : null,
                ]);
            }
        }
    }

    private function generateTextValue($field): ?string
    {
        $samples = [
            'Model' => 'Premium Edition',
            'Condition' => 'Excellent',
            'Type' => 'Sedan',
            'Brand' => 'Toyota',
            'Color' => 'White',
            'Size' => '2 Bedroom',
            'Furnished' => 'Partially Furnished',
            'Name' => 'Soccer Ball Pro',
        ];

        return $samples[$field->name] ?? 'Sample Value';
    }

    private function generateIntegerValue($field): ?int
    {
        $samples = [
            'Year' => 2020,
            'Mileage' => 45000,
            'Bedrooms' => 2,
            'Bathrooms' => 2,
            'Size (sqm)' => 120,
        ];

        return $samples[$field->name] ?? rand(1, 100);
    }

    private function generateDecimalValue($field): ?float
    {
        return round(rand(100, 10000) + (rand(0, 99) / 100), 2);
    }

    private function generateDateValue($field): ?string
    {
        return now()->subMonths(rand(1, 12))->toDateString();
    }

    private function generateSelectValue($field): ?int
    {
        // Get the first available option for this field
        $option = $field->options()->first();
        return $option?->id;
    }
}
