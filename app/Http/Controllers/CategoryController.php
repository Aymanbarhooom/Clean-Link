<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['index', 'show']);
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 10);
        $perPage = max(1, min($perPage, 100));
        $categories = Category::paginate($perPage);
        $user = auth()->user();
        if ($user->isAdmin() || $user->isCompanyManager() || $user->isRegionManager()) {
         return $this->successResponse($categories, 'Categories matrix retrieved successfully');   
        }
        return $this->successResponse(CategoryResource::collection($categories), 'Categories matrix retrieved successfully');
    }

    public function show(Request $request, Category $category): JsonResponse
    {
        $perPage = $request->integer('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $services = $category->services()->paginate($perPage);

        $category->setRelation('services', $services);
        $user = auth()->user();
        if ($user->isAdmin() || $user->isCompanyManager() || $user->isRegionManager()) {
         return $this->successResponse($category, 'Category specific parameters loaded');   
        }

        return $this->successResponse(new CategoryResource($category), 'Category specific parameters loaded');
    }


    public function store(Request $request): JsonResponse
    {
        if (!auth()->user()->isAdmin()) {
            return $this->errorResponse('Access restricted to administrative accounts only', 403);
        }
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('category_images', 'public');
            $validated['image'] = $path;
        }
        $validated = $request->validate([
            'name_ar' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
        ]);

        $category = Category::create($validated);
        return $this->successResponse($category, 'Category established inside index registries', 211);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        if (!auth()->user()->isAdmin()) {
            return $this->errorResponse('Access restricted to administrative accounts only', 403);
        }
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('category_images', 'public');
            $validated['image'] = $path;
        }
        $validated = $request->validate([
            'name_ar' => 'sometimes|string|max:255',
            'name_en' => 'sometimes|string|max:255',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
        ]);

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
}
