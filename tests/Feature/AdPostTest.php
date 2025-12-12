<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Category;
use App\Models\CategoryField;
use App\Models\CategoryFieldOption;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdPostTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_ad_success_with_dynamic_fields()
    {
        // Create user
        $user = User::factory()->create();

        // Create a category and fields
        $category = Category::create([
            'external_id' => 'test-cat-1',
            'name' => 'Test Category',
            'slug' => 'test-category',
        ]);

        // Required text field
        $fieldText = CategoryField::create([
            'category_id' => $category->id,
            'external_id' => 'model',
            'name' => 'model',
            'label' => 'Model',
            'field_type' => 'text',
            'is_required' => true,
        ]);

        // Select field with options
        $fieldSelect = CategoryField::create([
            'category_id' => $category->id,
            'external_id' => 'color',
            'name' => 'color',
            'label' => 'Color',
            'field_type' => 'select',
            'is_required' => false,
        ]);

        $optRed = CategoryFieldOption::create([
            'category_field_id' => $fieldSelect->id,
            'external_id' => 'red',
            'value' => 'red',
            'label' => 'Red',
        ]);

        // Build payload
        $payload = [
            'category_id' => $category->id,
            'title' => 'Nice Item',
            'description' => 'A very nice item description that is sufficiently long.',
            'price' => 123.45,
            'fields' => [
                'model' => 'XYZ-2000',
                'color' => $optRed->id,
            ],
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/ads', $payload);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id', 'title', 'description', 'price', 'fields'
            ]
        ]);
    }

    public function test_post_ad_validation_failure_missing_required_dynamic_field()
    {
        $user = User::factory()->create();

        $category = Category::create([
            'external_id' => 'test-cat-2',
            'name' => 'Test Category 2',
            'slug' => 'test-category-2',
        ]);

        CategoryField::create([
            'category_id' => $category->id,
            'external_id' => 'must_have',
            'name' => 'must_have',
            'label' => 'Must Have',
            'field_type' => 'text',
            'is_required' => true,
        ]);

        $payload = [
            'category_id' => $category->id,
            'title' => 'Bad Item',
            'description' => 'Short description but ok length for testing purposes.',
            'price' => 10,
            'fields' => [
                // missing 'must_have'
            ],
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/ads', $payload);

        $response->assertStatus(422);
        $response->assertJsonStructure(['success', 'message', 'errors']);
        $this->assertArrayHasKey('fields.must_have', $response->json('errors'));
    }
}
