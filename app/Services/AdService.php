<?php

namespace App\Services;

use App\Models\Ad;
use App\Models\AdFieldValue;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdService
{
    /**
     * Create a new ad with dynamic field values.
     */
    public function createAd(User $user, Category $category, array $adData, array $fieldData): Ad
    {
        try {
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
        } catch (Throwable $e) {
            Log::error('Failed to create ad', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * Update an existing ad and its field values.
     */
    public function updateAd(Ad $ad, array $adData, ?array $fieldData = null): Ad
    {
        try {
            return DB::transaction(function () use ($ad, $adData, $fieldData) {
                // Update main ad fields (only non-null values)
                $ad->update(array_filter([
                    'title' => $adData['title'] ?? null,
                    'description' => $adData['description'] ?? null,
                    'price' => $adData['price'] ?? null,
                    'status' => $adData['status'] ?? null,
                ], fn($value) => $value !== null));

                // Update dynamic fields if provided (null = not provided)
                if ($fieldData !== null) {
                    $this->saveDynamicFields($ad, $fieldData);
                }

                Log::info('Ad updated successfully', ['ad_id' => $ad->id]);

                return $ad->fresh(['category', 'fieldValues.categoryField', 'fieldValues.selectedOption']);
            });
        } catch (Throwable $e) {
            Log::error('Failed to update ad', ['ad_id' => $ad->id ?? null, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Save or update dynamic field values for an ad.
     *
     * @param Ad $ad
     * @param array $fieldData - keys should match canonical field keys (external_id|name|id)
     */
    private function saveDynamicFields(Ad $ad, array $fieldData): void
    {
        $category = $ad->category()->with('fields.options')->first();

        // Defensive: if no category loaded, nothing to save
        if (!$category) {
            Log::warning('Attempt to save dynamic fields but category not found', ['ad_id' => $ad->id]);
            return;
        }

        foreach ($category->fields as $field) {
            // canonical key for incoming payload — keep consistent with StoreAdRequest
            $key = $this->getFieldKey($field);

            // Skip if field not provided (note: to delete a value on update, pass explicit null and handle that case here)
            if (!array_key_exists($key, $fieldData)) {
                continue;
            }

            $value = $fieldData[$key];

            // Normalize empty strings to null to avoid invalid inserts for numeric/date fields
            if (is_string($value) && trim($value) === '') {
                $value = null;
            }

            // Create or update the field value record
            $adFieldValue = AdFieldValue::updateOrCreate(
                [
                    'ad_id' => $ad->id,
                    'category_field_id' => $field->id,
                ],
                []
            );

            // Attach categoryField relation so setValue() can inspect the field metadata
            $adFieldValue->setRelation('categoryField', $field);

            // Set the value (AdFieldValue::setValue handles type-specific assignment)
            $adFieldValue->setValue($value);

            // If the client explicitly sent null for a required field you may want to delete the record;
            // current behavior: store null values in the typed columns (setValue cleared other columns).
            $adFieldValue->save();

            Log::debug('Saved field value', [
                'ad_id' => $ad->id,
                'field_key' => $key,
                'field_type' => $field->field_type,
                'category_field_id' => $field->id,
            ]);
        }
    }

    /**
     * Compute canonical payload key for a category field (keep in sync with request logic).
     */
    private function getFieldKey($field): string
    {
        if (!empty($field->external_id)) {
            return (string) $field->external_id;
        }

        if (!empty($field->name)) {
            return (string) $field->name;
        }

        return (string) $field->id;
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
