<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\RegionResource;
use App\Http\Resources\ServiceResource;
use App\Models\Category;
use App\Models\Company;
use App\Models\Region;
use App\Models\Review;
use App\Models\Service;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        // 1. Offers: Services sorted by the largest absolute discount amount
        $offersCacheKey = 'homepage_offers';
        $offers = Cache::remember($offersCacheKey, now()->addHours(1), function () {
            return Service::where('discount', '>', 0)
                ->orderBy('discount', 'desc')
                ->take(3)
                ->get();
        });

        // 2. Premium Services: Highest customer rating scores
        $topServicesCacheKey = 'homepage_top_services';
        $topServices = Cache::remember($topServicesCacheKey, now()->addHours(1), function () {
            return Service::orderBy('rating', 'desc')
                ->take(3)
                ->get();
        });

        // 3. Categories: First 6 index parameters
        $categoriesCacheKey = 'homepage_categories';
        $categories = Cache::remember($categoriesCacheKey, now()->addHours(1), function () {
            return Category::take(6)->get();
        });

        // 4. Elite Companies: Highest rated corporate brands operating
        $topCompaniesCacheKey = 'homepage_top_companies';
        $topCompanies = Cache::remember($topCompaniesCacheKey, now()->addHours(1), function () {
            return Company::orderBy('rating', 'desc')
                ->take(6)
                ->get();
        });
        $topCompanies->load('workTimes');
        // Aggregate inside a structured layout matching your frontend requirement
        return $this->successResponse([
            'offers' => ServiceResource::collection($offers),
            'services' => ServiceResource::collection($topServices),
            'categories' => CategoryResource::collection($categories),
            'companies' => CompanyResource::collection($topCompanies),
        ], 'Home page aggregates loaded successfully');
    }

    public function search(Request $request): JsonResponse
{
    $request->validate([
        'query'           => 'required|string|max:255',
        'region_id'       => 'nullable|integer',
        'rating'          => 'nullable|numeric|min:0|max:5',
        'minimum_price'   => 'nullable|numeric|min:0',
        'maximum_price'   => 'nullable|numeric|min:0',
    ]);

    $searchQuery = $request->input('query');
    $regionId = $request->input('region_id');

    /*
    |--------------------------------------------------------------------------
    | Regions
    |--------------------------------------------------------------------------
    */

    $regionsQuery = Region::where(function ($query) use ($searchQuery) {
        $query->where('name_en', 'LIKE', "%{$searchQuery}%")
            ->orWhere('name_ar', 'LIKE', "%{$searchQuery}%");
    });

    if ($regionId) {
        $regionsQuery->where('id', $regionId);
    }

    $regions = $regionsQuery->with('manager')->get();

    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    */

    $categories = Category::where('name_en', 'LIKE', "%{$searchQuery}%")
        ->orWhere('name_ar', 'LIKE', "%{$searchQuery}%")
        ->get();

    /*
    |--------------------------------------------------------------------------
    | Companies
    |--------------------------------------------------------------------------
    */

    $companiesQuery = Company::where(function ($query) use ($searchQuery) {
        $query->where('name_en', 'LIKE', "%{$searchQuery}%")
            ->orWhere('name_ar', 'LIKE', "%{$searchQuery}%");
    });

    if ($regionId) {
        $companiesQuery->where('region_id', $regionId);
    }

    if ($request->filled('rating')) {
        $companiesQuery->where(
            'rating',
            '>=',
            (float) $request->input('rating')
        );
    }

    $companies = $companiesQuery
        ->with('workTimes')
        ->get();

    /*
    |--------------------------------------------------------------------------
    | Services
    |--------------------------------------------------------------------------
    */

    $servicesQuery = Service::query()
        ->where(function ($query) use ($searchQuery) {
            $query->where('name_en', 'LIKE', "%{$searchQuery}%")
                ->orWhere('name_ar', 'LIKE', "%{$searchQuery}%");
        });

    if ($regionId) {
        $servicesQuery->whereHas('company', function ($query) use ($regionId) {
            $query->where('region_id', $regionId);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Price overlap filter
    |--------------------------------------------------------------------------
    */

    if (
        $request->filled('minimum_price') &&
        $request->filled('maximum_price')
    ) {
        $userMin = (float) $request->input('minimum_price');
        $userMax = (float) $request->input('maximum_price');

        if ($userMin > $userMax) {
            [$userMin, $userMax] = [$userMax, $userMin];
        }

        $servicesQuery
            ->where('minimum_price', '<=', $userMax)
            ->where('maximum_price', '>=', $userMin);
    }

    /*
    |--------------------------------------------------------------------------
    | Rating filter
    |--------------------------------------------------------------------------
    */

    if ($request->filled('rating')) {
        $servicesQuery->where(
            'rating',
            '>=',
            (float) $request->input('rating')
        );
    }

    $services = $servicesQuery
        ->orderByDesc('rating')
        ->get();

    /*
    |--------------------------------------------------------------------------
    | Offers
    |--------------------------------------------------------------------------
    */

    $offers = $services
        ->where('discount', '>', 0)
        ->take(5)
        ->values();

    return $this->successResponse([
        'regions' => RegionResource::collection($regions),
        'categories' => CategoryResource::collection($categories),
        'companies' => CompanyResource::collection($companies),
        'services' => ServiceResource::collection($services),
        'offers' => ServiceResource::collection($offers),
    ], "Search index results generated for term: '{$searchQuery}'");
}


    public function getOffers(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 6);

        $offers = Service::where('discount', '>', 0)
            ->orderBy('discount', 'desc')
            ->paginate($perPage);

        $responseData = [
            'data' => ServiceResource::collection($offers->items()),
            'pagination' => [
                'current_page' => $offers->currentPage(),
                'per_page' => $offers->perPage(),
                'total' => $offers->total(),
                'last_page' => $offers->lastPage(),
                'from' => $offers->firstItem(),
                'to' => $offers->lastItem(),
                'has_more_pages' => $offers->hasMorePages(),
            ]
        ];

        return $this->successResponse($responseData, "Offers retrieved successfully");
    }

    public function userSummary(): JsonResponse
    {
        $user = auth()->user();

        $data = [
            'total_bookings' => $user->orders()->count(),
            'total_favorites' => $user->favorites()->count(),
            'total_reviews' => Review::where('client_id', $user->id)->count(),
        ];

        return $this->successResponse($data, 'User summary counts loaded successfully');
    }

    public function userReviews(): JsonResponse
    {
        $user = auth()->user();

        $reviews = Review::where('client_id', $user->id)
            ->with('reviewable')
            ->get();

        return $this->successResponse([
            'companies' => $reviews->where('reviewable_type', Company::class)->values()->toArray(),
            'services' => $reviews->where('reviewable_type', Service::class)->values()->toArray(),
        ], 'User reviews split by companies and services');
    }
}
