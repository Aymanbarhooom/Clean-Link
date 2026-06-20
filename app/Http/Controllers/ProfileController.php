<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Retrieve current authenticating session user's specific profile metrics.
     */
    public function show(): JsonResponse
    {
        $profile = auth()->user()->profile;
        
        if (!$profile) {
            return $this->errorResponse('Target operational matrix record entry corrupt or missing', 404);
        }

        return $this->successResponse($profile, 'User identity profile records fetched');
    }

    /**
     * Update current authenticated identity parameters cleanly.
     */
    public function update(Request $request): JsonResponse
    {
        $profile = auth()->user()->profile;

        if (!$profile) {
            return $this->errorResponse('Target profile sequence reference map missing', 404);
        }

        $validated = $request->validate([
            'image' => 'nullable|string',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:30',
        ]);

        $profile->update($validated);
        
        return $this->successResponse($profile, 'User metadata parameters modified successfully');
    }
}
