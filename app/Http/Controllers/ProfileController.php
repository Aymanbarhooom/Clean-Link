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
        // Get the authenticated user's profile or create a new one if it doesn't exist
        $profile = auth()->user()->profile()->firstOrCreate(['user_id' => auth()->id()]);

        // Validate the incoming request data
        $validated = $request->validate([
            'address' => 'nullable|regex:/^[a-zA-Z0-9\s\-_]+$/|max:500',
            'phone' => 'nullable|regex:/^[0-9\s\-\(\)]+$/|max:30',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
        ]);
        if($request->hasFile('image')) {
            $path = $request->file('image')->store('worker_profiles', 'public');
            $validated['image'] = $path;
        }

        // Update the profile with validated data
        $profile->update($validated);

        return $this->successResponse($profile, 'User metadata parameters modified successfully');
    }

}
