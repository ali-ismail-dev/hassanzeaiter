<?php
namespace App\Services;

use App\Models\Ad;
use App\Models\AdFieldValue;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdService
{
    /**
     * Create a new ad with dynamic field values.
     */
    public function createAd(User $user, Category $category, array $adData, array $fieldData): Ad
    {
        return DB::transaction(function () use ($user, $category, $adData, $fieldData) {
            // Create the main ad record
            $ad = Ad::create([
                'user_id' => $user->id,
                'category_id' => $category->id,
                'title' => $adData['title'],
                'description' => $adData['description'],
                'price' => $adData['price'] ?? null,
                'status' => 'active',
                'published_at' => now(),
            ]);

            // Save dynamic field values
            $this->saveDynamicFields($ad, $fieldData);

            Log::info('Ad created successfully', [
                'ad_id' => $ad->id,
                'user_id' => $user->id,
                'category_id' => $category->id,
            ]);

            return $ad->load(['category', 'fieldValues.categoryField', 'fieldValues.selectedOption']);
        });
    }

    /**
     * Update an existing ad and its field values.
     */
    public function updateAd(Ad $ad, array $adData, ?array $fieldData = null): Ad
    {
        return DB::transaction(function () use ($ad, $adData, $fieldData) {
            // Update main ad fields
            $ad->update(array_filter([
                'title' => $adData['title'] ?? null,
                'description' => $adData['description'] ?? null,
                'price' => $adData['price'] ?? null,
                'status' => $adData['status'] ?? null,
            ], fn($value) => $value !== null));

            // Update dynamic fields if provided
            if ($fieldData !== null) {
                $this->saveDynamicFields($ad, $fieldData);
            }

            Log::info('Ad updated successfully', ['ad_id' => $ad->id]);

            return $ad->fresh(['category', 'fieldValues.categoryField', 'fieldValues.selectedOption']);
        });
    }

    /**
     * Save or update dynamic field values for an ad.
     */
    private function saveDynamicFields(Ad $ad, array $fieldData): void
{
    $category = $ad->category()->with('fields.options')->first();

    foreach ($category->fields as $field) {
        // canonical key for incoming payload
        $key = $field->external_id ?: $field->name ?: $field->id;

        // Skip if field not provided (note: allow null values if provided)
        if (!array_key_exists($key, $fieldData)) {
            continue;
        }

        $value = $fieldData[$key];

        // Create or update the field value record
        $adFieldValue = AdFieldValue::updateOrCreate(
            [
                'ad_id' => $ad->id,
                'category_field_id' => $field->id,
            ],
            []
        );

        // Attach categoryField for setValue() logic
        $adFieldValue->setRelation('categoryField', $field);

        // For select/radio we expect option ID; for checkbox we expect array of option IDs
        $adFieldValue->setValue($value);
        $adFieldValue->save();

        Log::debug('Saved field value', [
            'ad_id' => $ad->id,
            'field_key' => $key,
            'field_type' => $field->field_type,
        ]);
    }
}


    /**
     * Delete an ad and all its field values.
     */
    public function deleteAd(Ad $ad): bool
    {
        return DB::transaction(function () use ($ad) {
            $adId = $ad->id;
            
            // Soft delete the ad (cascades to field values via DB)
            $deleted = $ad->delete();

            if ($deleted) {
                Log::info('Ad deleted successfully', ['ad_id' => $adId]);
            }

            return $deleted;
        });
    }
}