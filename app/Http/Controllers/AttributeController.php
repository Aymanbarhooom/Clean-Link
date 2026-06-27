<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttributeResource;
use App\Models\Attribute;
use App\Models\AttributeModel;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AttributeController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        // Enforce sanctum authentication for all actions
        $this->middleware('auth:sanctum');
    }

    /**
     * Display a listing of all global attributes.
     * Useful for Company Managers when setting up custom prices.
     */
    public function index(): JsonResponse
    {
        $attributes = AttributeModel::orderBy('created_at', 'desc')->get();
        return $this->successResponse(AttributeResource::collection($attributes), 'Global attribute dictionary retrieved successfully');
    }

    /**
     * Add a new global attribute to the system dictionary (Admin Only).
     */
    public function store(Request $request): JsonResponse
    {
        // Enforce administrative actor validation
        if (!auth()->user()->isAdmin()) {
            return $this->errorResponse('Access restricted to administrative accounts only', 403);
        }

        $validated = $request->validate([
            'name_ar' => 'required|string|max:255|unique:attributes,name_ar',
            'name_en' => 'required|string|max:255|unique:attributes,name_en',
            'type' => 'required|in:number,text,boolean',
        ]);

        $attribute = AttributeModel::create($validated);
        
        return $this->successResponse($attribute, 'New global attribute added successfully to the dictionary', 211);
    }

    /**
     * Remove a global attribute from the system dictionary (Admin Only).
     */
    public function destroy(AttributeModel $attribute): JsonResponse
    {
        // Enforce administrative actor validation
        if (!auth()->user()->isAdmin()) {
            return $this->errorResponse('Access restricted to administrative accounts only', 403);
        }

        // Deleting this will automatically cascade and clean up entries in the 'attribute_service' table
        $attribute->delete();

        return $this->successResponse([], 'Global attribute permanently removed from the system dictionary');
    }
}
