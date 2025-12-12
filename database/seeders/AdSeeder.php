<?php

namespace Database\Seeders;

use App\Models\Ad;
use App\Models\Category;
use App\Models\User;
use App\Models\AdFieldValue;
use Illuminate\Database\Seeder;

class AdSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $categories = Category::with('fields.options')->get();

        foreach ($users as $user) {
            foreach ($categories as $category) {
                // Random number of ads per user per category
                $adsCount = rand(1, 3);

                for ($i = 0; $i < $adsCount; $i++) {
                    $ad = Ad::create([
                        'user_id' => $user->id,
                        'category_id' => $category->id,
                        'title' => fake()->sentence(3),
                        'description' => fake()->paragraph(),
                        'price' => fake()->randomFloat(2, 10, 1000),
                        'status' => 'active',
                        'published_at' => now(),
                    ]);

                    // Generate dynamic field values
                    foreach ($category->fields as $field) {
                        $key = $field->name ?? $field->id;
                        $value = $this->generateFieldValue($field);
                        
                        $adFieldValue = AdFieldValue::create([
                            'ad_id' => $ad->id,
                            'category_field_id' => $field->id,
                        ]);

                        $adFieldValue->setRelation('categoryField', $field);
                        $adFieldValue->setValue($value);
                        $adFieldValue->save();
                    }
                }
            }
        }
    }

    private function generateFieldValue($field)
    {
        return match($field->field_type) {
            'text','textarea','email','url' => fake()->word(),
            'number' => fake()->numberBetween(1, 100),
            'decimal','price' => fake()->randomFloat(2, 10, 1000),
            'date' => fake()->date(),
            'checkbox' => $field->options->pluck('id')->random(rand(1, min(3, $field->options->count())))->toArray(),
            'radio','select' => $field->options->random()->id ?? null,
            'boolean' => fake()->boolean(),
            default => null,
        };
    }
}
