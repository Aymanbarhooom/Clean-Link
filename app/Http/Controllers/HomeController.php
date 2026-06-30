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
    $offers = Service::where('discount', '>', 0)
        ->orderBy('discount', 'desc')
        ->take(3)
        ->get();

    // 2. Premium Services: Highest customer rating scores
    $topServices = Service::orderBy('rating', 'desc')
        ->take(3)
        ->get();

    // 3. Categories: First 6 index parameters
    $categories = Category::take(6)->get();

    // 4. Elite Companies: Highest rated corporate brands operating
    $topCompanies = Company::orderBy('rating', 'desc')
        ->take(6)
        ->get();

    // Aggregate inside a structured layout matching your frontend requirement
    return $this->successResponse([ 
            'offers'     => ServiceResource::collection($offers), 
            'services'   => ServiceResource::collection($topServices), 
            'categories' => CategoryResource::collection($categories), 
            'companies'  => CompanyResource::collection($topCompanies), 
        ], 'Home page aggregates loaded successfully'); 
    } 

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|max:255',
        ]);

        $searchQuery = $request->input('query');
        $locale = app()->getLocale(); // Detected automatically from 'Accept-Language' header via Middleware

        // 1. Search in Regions
        $regions = Region::where("name_{$locale}", 'LIKE', "%{$searchQuery}%")
            ->get();

        // 2. Search in Categories
        $categories = Category::where("name_{$locale}", 'LIKE', "%{$searchQuery}%")
            ->orWhere("description_{$locale}", 'LIKE', "%{$searchQuery}%")
            ->get();

        // 3. Search in Companies
        $companies = Company::where("name_{$locale}", 'LIKE', "%{$searchQuery}%")
            ->orWhere("description_{$locale}", 'LIKE', "%{$searchQuery}%")
            ->get();

        // 4. Search in Services (General)
        $services = Service::where("name_{$locale}", 'LIKE', "%{$searchQuery}%")
            ->orWhere("description_{$locale}", 'LIKE', "%{$searchQuery}%")
            ->get();

        // 5. Filter Offers out of the matched services to create an isolated pool
        $offers = $services->where('discount', '>', 0)->take(5)->values();

        // Structural Payload Response using your explicit API Resources Mapping
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
        return $this->successResponse($offers, "Offers retrieved successfully");
    }
}
