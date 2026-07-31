<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryNameResource;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['index', 'show']);
    }


    public function index(Request $request): JsonResponse
    {
        // عدد العناصر في كل صفحة - يمكنك تعديله حسب الحاجة
        $perPage = $request->get('per_page', 10);

        // استخدام paginate بدلاً من cursorPaginate
        $categories = Category::orderBy('id', 'asc')->paginate($perPage);

        $responseData = [
            'data' => CategoryResource::collection($categories->items()),
            'pagination' => [
                'current_page' => $categories->currentPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
                'last_page' => $categories->lastPage(),
                'from' => $categories->firstItem(),
                'to' => $categories->lastItem(),
                'has_more_pages' => $categories->hasMorePages(),
            ]
        ];

        return $this->successResponse($responseData, 'Categories retrieved successfully');
    }



    public function show(Request $request, Category $category): JsonResponse
    {
        $cacheKey = 'category_' . $category->id . '_with_services_images';

        $cachedCategoryData = Cache::remember($cacheKey, now()->addDay(), function () use ($category) {
            $category->load('services.images');
            return $category;
        });

        $user = auth()->user();

        if ($user && ($user->isAdmin() || $user->isCompanyManager() || $user->isRegionManager())) {
            return $this->successResponse($cachedCategoryData, 'Category specific parameters loaded');
        }

        return $this->successResponse(new CategoryResource($cachedCategoryData), 'Category specific parameters loaded');
    }


    public function store(Request $request): JsonResponse
    {
        if (!auth()->user()->isAdmin()) {
            return $this->errorResponse('Access restricted to administrative accounts only', 403);
        }
        $validated = $request->validate([
            'name_ar' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
        ]);
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('category_images', 'public');
            $validated['image'] = $path;
        }

        $category = Category::create($validated);
        return $this->successResponse($category, 'Category established inside index registries', 211);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        if (!auth()->user()->isAdmin()) {
            return $this->errorResponse('Access restricted to administrative accounts only', 403);
        }

        $validated = $request->validate([
            'name_ar' => 'sometimes|string|max:255',
            'name_en' => 'sometimes|string|max:255',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
        ]);
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('category_images', 'public');
            $validated['image'] = $path;
        }

        $category->update($validated);
        return $this->successResponse($category, 'Category structural configuration parameters modified');
    }

    public function destroy(Category $category): JsonResponse
    {
        if (!auth()->user()->isAdmin()) {
            return $this->errorResponse('Access restricted to administrative accounts only', 403);
        }

        $category->delete();
        return $this->successResponse([], 'Category scrubbed from architecture records');
    }

    public function getCategoriesNames(): JsonResponse
    {
        $regions = Category::all();
        return $this->successResponse(CategoryNameResource::collection($regions), 'Categories names retrieved successfully');
    }
}
