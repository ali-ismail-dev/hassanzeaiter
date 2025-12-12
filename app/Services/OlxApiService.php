<?php

// app/Services/OlxApiService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Http\Client\RequestException;

class OlxApiService
{
    private const CATEGORIES_ENDPOINT = 'https://www.olx.com.lb/api/categories/';
    private const CATEGORY_FIELDS_ENDPOINT = 'https://www.olx.com.lb/api/categoryFields';
    private const CACHE_TTL = 86400; // 24 hours in seconds
    private const TIMEOUT = 30; // seconds

    /**
     * Fetch all categories from OLX API with caching.
     */

public function fetchCategories(bool $forceRefresh = false): array
{
    $cacheKey = 'olx_categories';

    if ($forceRefresh) {
        Cache::forget($cacheKey);
    }

    return Cache::remember($cacheKey, self::CACHE_TTL, function () {
        try {
            Log::info('Fetching categories from OLX API');

            $response = Http::timeout(self::TIMEOUT)
                ->retry(3, 1000)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Laravel Sync)',
                    'Accept' => 'application/json',
                ])
                ->get(self::CATEGORIES_ENDPOINT);

            if (!$response->successful()) {
                // Log and return empty array instead of throwing
                Log::warning('OLX categories request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            $data = $response->json();

            if (!isset($data['data'])) {
                Log::warning('OLX categories response missing data key', ['body' => $data]);
                return [];
            }

            Log::info('Successfully fetched ' . count($data['data']) . ' categories');

            return $data['data'];
        } catch (RequestException $e) {
            Log::error('HTTP RequestException fetching categories', [
                'message' => $e->getMessage(),
            ]);
            return [];
        } catch (\Throwable $e) {
            Log::error('Unexpected error fetching categories', [
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    });
}


    /**
     * Fetch category fields for specific category IDs with caching.
     */
    public function fetchCategoryFields(array $categoryExternalIds, bool $forceRefresh = false): array
    {
        $cacheKey = 'olx_category_fields_' . md5(implode(',', $categoryExternalIds));

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($categoryExternalIds) {
            try {
                $categoryIdsString = implode(',', $categoryExternalIds);
                
                Log::info('Fetching category fields from OLX API', [
                    'categories' => $categoryIdsString,
                ]);

                $response = Http::timeout(self::TIMEOUT)
                    ->retry(3, 1000)
                    ->get(self::CATEGORY_FIELDS_ENDPOINT, [
                        'categoryExternalIDs' => $categoryIdsString,
                        'includeWithoutCategory' => 'true',
                        'splitByCategoryIDs' => 'true',
                        'flatChoices' => 'true',
                        'groupChoicesBySection' => 'true',
                        'flat' => 'true',
                    ]);

                if (!$response->successful()) {
                    throw new Exception("OLX API returned status: {$response->status()}");
                }

                $data = $response->json();

                if (!isset($data['data'])) {
                    throw new Exception('Invalid response structure from OLX API');
                }

                Log::info('Successfully fetched category fields', [
                    'categories_count' => count($data['data']),
                ]);

                return $data['data'];
            } catch (Exception $e) {
                Log::error('Failed to fetch category fields from OLX API', [
                    'error' => $e->getMessage(),
                    'categories' => implode(',', $categoryExternalIds),
                ]);
                throw $e;
            }
        });
    }

    /**
     * Fetch fields for a single category.
     */
    public function fetchFieldsForCategory(string $categoryExternalId, bool $forceRefresh = false): array
    {
        $allFields = $this->fetchCategoryFields([$categoryExternalId], $forceRefresh);
        return $allFields[$categoryExternalId] ?? [];
    }

    /**
     * Clear all OLX API caches.
     */
    public function clearCache(): void
    {
        Cache::forget('olx_categories');
        
        // Clear all category fields caches (you might want to track these keys)
        Log::info('Cleared OLX API caches');
    }

    /**
     * Map OLX field type to our internal field type.
     */
    public function mapFieldType(string $olxType): string
    {
        return match (strtolower($olxType)) {
            'input', 'string' => 'text',
            'textarea' => 'textarea',
            'integer', 'number' => 'number',
            'select', 'dropdown' => 'select',
            'radio' => 'radio',
            'checkbox', 'multiselect' => 'checkbox',
            'date' => 'date',
            'email' => 'email',
            'url' => 'url',
            default => 'text',
        };
    }
}