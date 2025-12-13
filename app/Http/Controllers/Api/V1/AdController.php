<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdRequest;
use App\Http\Requests\UpdateAdRequest;
use App\Http\Resources\AdResource;
use App\Http\Resources\AdCollection;
use App\Models\Ad;
use App\Services\AdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Throwable;
use Illuminate\Support\Facades\Log;

class AdController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private AdService $adService
    ) {
        // Keep controller thin — service handles the heavy lifting
    }

    /**
     * POST /api/v1/ads
     */
    public function store(StoreAdRequest $request): JsonResponse
    {
        try {
            $category = $request->getCategory();
            $fieldData = $request->getValidatedFields();
            $adPayload = $request->validated(); // includes base fields (title, description, price, category_id)

            $ad = $this->adService->createAd(
                user: $request->user(),
                category: $category,
                adData: [
                    'title' => $adPayload['title'],
                    'description' => $adPayload['description'],
                    'price' => $adPayload['price'] ?? null,
                ],
                fieldData: $fieldData
            );

            return (new AdResource($ad))
                ->response()
                ->setStatusCode(201)
                ->header('Location', route('ads.show', ['ad' => $ad->id]));
        } catch (Throwable $e) {
    Log::error('Ad creation failed', [
        'user_id' => $request->user()?->id,
        'category_id' => $request->input('category_id'),
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);

    return response()->json([
        'success' => false,
        'message' => 'Failed to create ad. If this persists, contact the dev team.',
    ], 500);
}
    }

    /**
     * GET /api/v1/my-ads
     */
    public function myAds(Request $request): AdCollection
    {
        $perPage = (int) min($request->input('per_page', 15), 100);

        $ads = Ad::with([
                'category',
                'user',
                'fieldValues.categoryField.options',
                'fieldValues.selectedOption'
            ])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate($perPage);

        return new AdCollection($ads);
    }

    /**
     * GET /api/v1/ads/{ad}
     */
    public function show(Ad $ad): AdResource
    {
        $ad->incrementViews();

        $ad->load([
            'category',
            'user',
            'fieldValues.categoryField.options',
            'fieldValues.selectedOption'
        ]);

        return new AdResource($ad);
    }

    /**
     * PUT/PATCH /api/v1/ads/{ad}
     */
    public function update(UpdateAdRequest $request, Ad $ad): AdResource
{
    $this->authorize('update', $ad);

    $payload = $request->validated();

    $updatedAd = $this->adService->updateAd(
        ad: $ad,
        adData: [
            'title' => $payload['title'] ?? null,
            'description' => $payload['description'] ?? null,
            'price' => $payload['price'] ?? null,
            'status' => $payload['status'] ?? null,
        ],
        fieldData: $payload['fields'] ?? null
    );

    return new AdResource($updatedAd);
}


    /**
     * DELETE /api/v1/ads/{ad}
     */
    public function destroy(Request $request, Ad $ad): JsonResponse
    {
        $this->authorize('delete', $ad);

        $this->adService->deleteAd($ad);

        return response()->json([
            'success' => true,
            'message' => 'Ad deleted successfully',
        ]);
    }

    /**
     * GET /api/v1/ads
     */
    public function index(Request $request): AdCollection
    {
        $perPage = (int) min($request->input('per_page', 15), 100);

        $query = Ad::with([
                'category',
                'user',
                'fieldValues.categoryField.options',
                'fieldValues.selectedOption'
            ])->active();

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->input('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->input('max_price'));
        }

        $allowedSorts = ['created_at', 'price', 'published_at', 'views_count'];
        $sortBy = in_array($request->input('sort_by', 'created_at'), $allowedSorts) ? $request->input('sort_by') : 'created_at';
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $ads = $query->paginate($perPage);

        return new AdCollection($ads);
    }
}
