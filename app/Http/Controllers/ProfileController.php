<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Location;
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
        $profile = auth()->user()->profile()->firstOrCreate(['user_id' => auth()->id()]);

        $validated = $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|regex:/^[0-9\s\-\(\)]+$/|max:30',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('worker_profiles', 'public');
            $validated['image'] = $path;
        }

        if (array_key_exists('latitude', $validated) || array_key_exists('longitude', $validated)) {
            $latitude = $validated['latitude'] ?? $profile->latitude ?? null;
            $longitude = $validated['longitude'] ?? $profile->longitude ?? null;

            if ($latitude !== null && $longitude !== null) {
                $formattedAddress = sprintf('%.6f, %.6f', (float) $latitude, (float) $longitude);
                $validated['address'] = $formattedAddress;

                auth()->user()->locations()->updateOrCreate(
                    ['name' => 'Home'],
                    [
                        'name' => 'Home',
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'address' => $formattedAddress,
                    ]
                );
            }
        }

        $profile->update($validated);

        return $this->successResponse($profile, 'User metadata parameters modified successfully');
    }

}
