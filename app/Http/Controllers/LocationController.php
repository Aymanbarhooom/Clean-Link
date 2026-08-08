<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Traits\ApiResponse;

class LocationController extends Controller
{

    use ApiResponse;
    /**
     * Display a listing of the user's locations.
     */
    public function index()
    {
        $locations = Location::where('user_id', Auth::id())->get();
        
        return $this->successResponse($locations, 'Locations retrieved successfully');
    }

    /**
     * Store a newly created location in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $location = Location::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'] ?? 'Home',
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
        ]);

        return $this->successResponse($location, 'Location created successfully', 201); 
    }

    /**
     * Display the specified location.
     */
    public function show(Location $location)
    {
        // Ensure the location belongs to the authenticated user
        if ($location->user_id !== Auth::id()) {
            return $this->error('Unauthorized', 403);
        }

        return $this->successResponse($location, 'Location retrieved successfully');
    }

    /**
     * Update the specified location in storage.
     */
    public function update(Request $request, Location $location)
    {
        // Ensure the location belongs to the authenticated user
        if ($location->user_id !== Auth::id()) {
            return $this->error('Unauthorized', 403);
        }
               
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
        ]);

        $location->update($validated);

            return $this->successResponse($location, 'Location updated successfully');
        }

    /**
     * Remove the specified location from storage.
     */
    public function destroy(Location $location)
    {
        // Ensure the location belongs to the authenticated user
        if ($location->user_id !== Auth::id()) {
            return $this->error('Unauthorized', 403);
        }
               
        $location->delete();

        return $this->successResponse(null, 'Location deleted successfully');
    }

    /**
     * Get nearest locations based on coordinates.
     */
    public function nearest(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:1|max:100', // in kilometers
        ]);

        $latitude = $request->latitude;
        $longitude = $request->longitude;
        $radius = $request->radius ?? 10; // default 10km

        $locations = Location::selectRaw("
            *,
            (6371 * acos(
                cos(radians(?)) * 
                cos(radians(latitude)) * 
                cos(radians(longitude) - radians(?)) + 
                sin(radians(?)) * 
                sin(radians(latitude))
            )) AS distance
        ", [$latitude, $longitude, $latitude])
        ->where('user_id', Auth::id())
        ->having('distance', '<=', $radius)
        ->orderBy('distance')
        ->get();

        return response()->json([
            'success' => true,
            'data' => $locations,
            'meta' => [
                'search_latitude' => $latitude,
                'search_longitude' => $longitude,
                'radius' => $radius . 'km'
            ]
        ]);
    }

    /**
     * Get paginated locations for the authenticated user.
     */
    public function paginated(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        
        $locations = Location::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $locations
        ]);
    }
}