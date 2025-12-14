<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;

class OlxApiService
{
    private const CATEGORIES_ENDPOINT = 'https://www.olx.com.lb/api/categories';
    private const CATEGORY_FIELDS_ENDPOINT = 'https://www.olx.com.lb/api/categoryFields';
    private const CACHE_TTL = 86400; // 24 hours
    private const TIMEOUT = 30; // seconds

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
                        'User-Agent' => 'Laravel-OLX-Sync/1.0',
                        'Accept' => 'application/json',
                    ])
                    ->get(self::CATEGORIES_ENDPOINT);

                Log::debug('OLX categories response', [
                    'status' => $response->status(),
                    'body_snippet' => is_string($response->body()) ? mb_substr($response->body(), 0, 2000) : null,
                ]);

                if (!$response->successful()) {
                    Log::warning('OLX categories request failed', ['status' => $response->status()]);
                    return [];
                }

                $data = $response->json();
                if (!is_array($data)) {
                    Log::warning('OLX categories returned non-array response', ['type' => gettype($data)]);
                    return [];
                }

                // The API returns an array of categories directly (no 'data' wrapper at root level)
                // If it's wrapped in a 'data' key, unwrap it; otherwise use as-is
                if (isset($data['data']) && is_array($data['data'])) {
                    return $data['data'];
                }
                
                // Check if this is already an array of category objects (has 'id' and 'name' properties)
                if (count($data) > 0 && isset($data[0]['id'])) {
                    return $data;
                }

                Log::warning('OLX categories returned unexpected structure', ['body' => $data]);
                return [];
            } catch (RequestException $e) {
                Log::error('HTTP RequestException fetching categories', ['message' => $e->getMessage()]);
                return [];
            } catch (\Throwable $e) {
                Log::error('Unexpected error fetching categories', ['message' => $e->getMessage()]);
                return [];
            }
        });
    }

    /**
     * Fetch category fields for specific category IDs with caching.
     * Returns an associative array keyed by external category id.
     */
    public function fetchCategoryFields(array $categoryExternalIds, bool $forceRefresh = false): array
    {
        // sanitize ids
        $categoryExternalIds = array_values(array_filter(array_map(fn($v) => is_scalar($v) ? trim((string)$v) : null, $categoryExternalIds)));
        if (empty($categoryExternalIds)) {
            return [];
        }

        $cacheKey = 'olx_category_fields_' . md5(implode(',', $categoryExternalIds));

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($categoryExternalIds) {
            try {
                $categoryIdsString = implode(',', $categoryExternalIds);

                Log::info('Fetching category fields from OLX API', ['categories' => $categoryIdsString]);

                $response = Http::timeout(self::TIMEOUT)
                    ->retry(3, 1000)
                    ->withHeaders([
                        'User-Agent' => 'Laravel-OLX-Sync/1.0',
                        'Accept' => 'application/json',
                    ])
                    ->get(self::CATEGORY_FIELDS_ENDPOINT, [
                        'categoryExternalIDs' => $categoryIdsString,
                        'includeWithoutCategory' => 'true',
                        'splitByCategoryIDs' => 'true',
                        'flatChoices' => 'true',
                        'groupChoicesBySection' => 'true',
                        'flat' => 'true',
                    ]);

                Log::debug('OLX categoryFields response', [
                    'status' => $response->status(),
                    'body_snippet' => is_string($response->body()) ? mb_substr($response->body(), 0, 2000) : null,
                ]);

                if (!$response->successful()) {
                    Log::warning('OLX categoryFields request failed', ['status' => $response->status()]);
                    return [];
                }

                $data = $response->json();
                
                // The API response structure is:
                // {
                //   "9": {
                //     "model": { attribute: "model", filterType: "...", choices: {...} },
                //     "make": { attribute: "make", filterType: "...", choices: {...} },
                //     ...
                //   },
                //   "70": { ... },
                //   ...
                // }
                
                if (!is_array($data)) {
                    Log::warning('OLX categoryFields returned non-array response');
                    return [];
                }

                // Normalize to map[externalId] => fields
                $result = [];
                foreach ($data as $externalId => $categoryFieldsData) {
                    if (is_array($categoryFieldsData)) {
                        $result[(string)$externalId] = $categoryFieldsData;
                    }
                }

                Log::debug('CategoryFields result', ['resultKeys' => array_keys($result), 'count' => count($result)]);
                return $result;
            } catch (RequestException $e) {
                Log::error('HTTP RequestException fetching category fields', ['message' => $e->getMessage()]);
                return [];
            } catch (\Throwable $e) {
                Log::error('Unexpected error fetching category fields', ['message' => $e->getMessage()]);
                return [];
            }
        });
    }

    public function fetchFieldsForCategory(string $categoryExternalId, bool $forceRefresh = false): array
    {
        $all = $this->fetchCategoryFields([$categoryExternalId], $forceRefresh);
        return $all[$categoryExternalId] ?? [];
    }

    public function clearCache(): void
    {
        Cache::forget('olx_categories');
        // Note: category fields caches are keyed per idset; clearing all would require tracking keys.
        Log::info('Cleared OLX API cache (categories)');
    }

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