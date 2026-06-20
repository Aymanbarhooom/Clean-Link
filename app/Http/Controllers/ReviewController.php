<?php

namespace App\Http\Controllers;


use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Company;
use App\Models\Service;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['index']);
    }

    /**
     * Display a listing of reviews for a specific Company or Service.
     * URL Example: /api/reviews?type=company&id=1 OR /api/reviews?type=service&id=5
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:company,service',
            'id' => 'required|integer',
        ]);

        $modelType = $request->type === 'company' ? Company::class : Service::class;
        
        // Eager load the client profile details for the frontend mapping requirements
        $reviews = Review::where('reviewable_type', $modelType)
            ->where('reviewable_id', $request->id)
            ->with('client.profile')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse($reviews, 'Reviews lookup index fetched successfully');
    }

    /**
     * Store a newly created review in storage.
     */
    public function store(Request $request): JsonResponse
    {
        if (auth()->user()->role !== 'client') {
            return $this->errorResponse('Only clients are authorized to post reviews', 403);
        }

        $validated = $request->validate([
            'type' => 'required|in:company,service',
            'id' => 'required|integer',
            'comment' => 'nullable|string|max:1000',
            'rating' => 'required|integer|between:1,5',
        ]);

        $modelClass = $validated['type'] === 'company' ? Company::class : Service::class;
        $reviewableEntity = $modelClass::find($validated['id']);

        if (!$reviewableEntity) {
            return $this->errorResponse('Target review entity not found', 404);
        }

        // Create the polymorphic review mapping
        $review = new Review([
            'client_id' => auth()->id(),
            'comment' => $validated['comment'],
            'rating' => $validated['rating']
        ]);

        $reviewableEntity->reviews()->save($review);

        // Explicitly trigger average rating recalculation on the model
        $reviewableEntity->recalculateRating();

        return $this->successResponse($review->load('client.profile'), 'Review submitted successfully', 211);
    }
}

