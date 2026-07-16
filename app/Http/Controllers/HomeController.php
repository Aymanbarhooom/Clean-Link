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
use App\Models\Service;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    use ApiResponse;

    /**
     * Aggregated Home Page Data for Mobile App Client
     * Route: GET /api/home-page
     */
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
            'query' => 'required|string|max:255',
            'region_id' => 'nullable|numeric', // Enforce numeric region ID validation
        ]);

        $searchQuery = $request->input('query');
        $regionId = $request->input('region_id');

        // 1. Search in Regions
        $regionsQuery = Region::where(function ($query) use ($searchQuery) {
            $query->where("name_en", 'LIKE', "%{$searchQuery}%")
                ->orWhere("name_ar", 'LIKE', "%{$searchQuery}%");
        });

        if ($regionId) {
            $regionsQuery->where('id', (int) $regionId);
        }
        $regions = $regionsQuery->with('manager')->get();

        // 2. Search in Categories
        $categories = Category::where("name_en", 'LIKE', "%{$searchQuery}%")
            ->orWhere("name_ar", 'LIKE', "%{$searchQuery}%")
            ->get();

        // 3. Search in Companies (Filtered by Region & Rating)
        $companiesQuery = Company::where(function ($query) use ($searchQuery) {
            $query->where("name_en", 'LIKE', "%{$searchQuery}%")
                ->orWhere("name_ar", 'LIKE', "%{$searchQuery}%");
        });

        if ($regionId) {
            $companiesQuery->where('region_id', (int) $regionId);
        }

        if ($request->filled('rating')) {
            $rating = (float) $request->input('rating');
            if (fmod($rating, 1) === 0.0) {
                $companiesQuery->where('rating', $rating);
            } else {
                $companiesQuery->where('rating', '>=', floor($rating))
                    ->where('rating', '<', ceil($rating));
            }
        }
        $companies = $companiesQuery->get();
        $companies->load('workTimes');

        // 4. Search in Services (Filtered by Region & Rating)
        $servicesQuery = Service::query()
            ->where(function ($query) use ($searchQuery) {
                $query->where("name_en", 'LIKE', "%{$searchQuery}%")
                    ->orWhere("name_ar", 'LIKE', "%{$searchQuery}%");
            });

        if ($regionId) {
            $servicesQuery->whereHas('company', function ($query) use ($regionId) {
                $query->where('region_id', (int) $regionId);
            });
        }

        if ($request->filled('price_range')) {
            $priceRange = trim((string) $request->input('price_range'));
            if (preg_match('/^\s*(\d+(?:\.\d+)?)\s*-\s*(\d+(?:\.\d+)?)\s*\$?\s*$/i', $priceRange, $matches)) {
                $servicesQuery->whereBetween('price', [(float) $matches[1], (float) $matches[2]]);
            }
        }

        if ($request->filled('rating')) {
            $rating = (float) $request->input('rating');
            if (fmod($rating, 1) === 0.0) {
                $servicesQuery->where('rating', $rating);
            } else {
                $servicesQuery->where('rating', '>=', floor($rating))
                    ->where('rating', '<', ceil($rating));
            }
        }

        $services = $servicesQuery
            ->orderBy('rating', 'desc')
            ->get();

        // 5. Filter Offers
        $offers = $services->where('discount', '>', 0)->take(5)->values();

        return $this->successResponse([
            'regions' => RegionResource::collection($regions),
            'categories' => CategoryResource::collection($categories),
            'companies' => CompanyResource::collection($companies),
            'services' => ServiceResource::collection($services),
            'offers' => ServiceResource::collection($offers),
        ], "Search index results generated for term: '{$searchQuery}'");
    }


    public function getoffers(): JsonResponse
    {
        $offers = Service::where('discount', '>', 0)
            ->orderBy('discount', 'desc')
            ->get();
        return $this->successResponse(ServiceResource::collection($offers), "Offers retrieved successfully");
    }
}
