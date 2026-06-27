<?php

// app/Http/Controllers/Api/HomeController.php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\ServiceResource;
use App\Models\Category;
use App\Models\Company;
use App\Models\Service;
use App\Traits\ApiResponse;
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

}
