<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdRequest;
use App\Http\Requests\UpdateAdRequest;
use App\Http\Resources\AdResource;
use App\Http\Resources\AdCollection;
use App\Models\Ad;
use App\Services\AdService;
use Illuminate\Http\Request;

class AdController extends Controller
{
    public function __construct(
        private AdService $adService
    ) {}

    /**
     * POST /api/v1/ads
     * Create a new ad with dynamic fields.
     */
    public function store(StoreAdRequest $request)
    {
        $category = $request->getCategory();
        $fieldData = $request->getValidatedFields();
        
        $ad = $this->adService->createAd(
            user: $request->user(),
            category: $category,
            adData: $request->only(['title', 'description', 'price']),
            fieldData: $fieldData
        );

        return (new AdResource($ad))
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('ads.show', $ad->id));
    }

    /**
     * GET /api/v1/my-ads
     * List all ads posted by authenticated user (paginated).
     */
    public function myAds(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $perPage = min($perPage, 100); // Max 100 per page

        $ads = Ad::with(['category', 'fieldValues.categoryField', 'fieldValues.selectedOption'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate($perPage);

        return new AdCollection($ads);
    }

    /**
     * GET /api/v1/ads/{id}
     * View a specific ad with all details.
     */
    public function show(Ad $ad)
    {
        // Increment view count
        $ad->incrementViews();

        // Load relationships
        $ad->load([
            'category',
            'user',
            'fieldValues.categoryField',
            'fieldValues.selectedOption'
        ]);

        return new AdResource($ad);
    }

    /**
     * PUT/PATCH /api/v1/ads/{id}
     * Update an existing ad.
     */
    public function update(UpdateAdRequest $request, Ad $ad)
    {
        $fieldData = $request->has('fields') ? $request->input('fields') : null;
        
        $updatedAd = $this->adService->updateAd(
            ad: $ad,
            adData: $request->only(['title', 'description', 'price', 'status']),
            fieldData: $fieldData
        );

        return new AdResource($updatedAd);
    }

    /**
     * DELETE /api/v1/ads/{id}
     * Delete an ad (soft delete).
     */
    public function destroy(Request $request, Ad $ad)
    {
        // Authorization check
        if ($ad->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete this ad.',
            ], 403);
        }

        $this->adService->deleteAd($ad);

        return response()->json([
            'success' => true,
            'message' => 'Ad deleted successfully',
        ]);
    }

    /**
     * GET /api/v1/ads
     * List all active ads (public endpoint - optional).
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $perPage = min($perPage, 100);

        $query = Ad::with(['category', 'user', 'fieldValues.categoryField', 'fieldValues.selectedOption'])
            ->active();

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        // Search in title/description
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Price range
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->input('min_price'));
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->input('max_price'));
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $ads = $query->paginate($perPage);

        return new AdCollection($ads);
    }
}