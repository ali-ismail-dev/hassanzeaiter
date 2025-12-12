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
    ) {
        // You can register a middleware here if needed, e.g. throttle
        // $this->middleware('throttle:60,1')->only(['store', 'update']);
    }

    /**
     * POST /api/v1/ads
     */
    public function store(StoreAdRequest $request)
    {
        // validated category loaded inside the request
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
            ->header('Location', route('ads.show', ['ad' => $ad->id]));
    }

    /**
     * GET /api/v1/my-ads
     */
    public function myAds(Request $request)
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
    public function show(Ad $ad)
    {
        // No authorization here (publicly viewable)
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
    public function update(UpdateAdRequest $request, Ad $ad)
    {
        // Policy checks: only owner (or admin via policy) can update
        $this->authorize('update', $ad);

        $fieldData = $request->has('fields') ? $request->input('fields') : null;

        $updatedAd = $this->adService->updateAd(
            ad: $ad,
            adData: $request->only(['title', 'description', 'price', 'status']),
            fieldData: $fieldData
        );

        return new AdResource($updatedAd);
    }

    /**
     * DELETE /api/v1/ads/{ad}
     */
    public function destroy(Request $request, Ad $ad)
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
     * Public listing of active ads with filters.
     */
    public function index(Request $request)
    {
        $perPage = (int) min($request->input('per_page', 15), 100);

        $query = Ad::with([
                'category',
                'user',
                'fieldValues.categoryField.options',
                'fieldValues.selectedOption'
            ])->active();

        // Filters
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

        // Safe sorting (whitelist)
        $allowedSorts = ['created_at', 'price', 'published_at', 'views_count'];
        $sortBy = in_array($request->input('sort_by', 'created_at'), $allowedSorts) ? $request->input('sort_by') : 'created_at';
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $ads = $query->paginate($perPage);

        return new AdCollection($ads);
    }
}
