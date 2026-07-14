<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegionNameResource;
use App\Http\Resources\RegionResource;
use App\Models\Region;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class RegionController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // --- Region Managers Management (Admin Area) ---

    public function addManager(Request $request): JsonResponse
    {
        $this->authorize('create', [User::class, 'region_manager']);

        $validated = $request->validate([
            'fullname' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
        ]);

        $manager = User::create([
            'fullname' => $validated['fullname'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'region_manager',
        ]);
        
        $manager->profile()->create();

        return $this->successResponse($manager, 'Region Manager added successfully', 211);
    }

    public function getManagers(): JsonResponse
    {
        $managers = User::where('role', 'region_manager')->with('profile')->get();
            return $this->successResponse($managers, 'Region Managers list retrieved'); 
           
    }

    public function deleteManager(User $manager): JsonResponse
    {
        $this->authorize('delete', $manager);
        
        $manager->delete();
        return $this->successResponse([], 'Region Manager deleted successfully');
    }

    // --- Regions Boundary Architecture Management ---

    public function addRegion(Request $request): JsonResponse
    {
        // Handled via standard structural role checking rule inside controllers or custom policy
        if (!auth()->user()->isAdmin()) {
            return $this->errorResponse('Unauthorized operation access boundary profile mismatch', 403);
        }

        $validated = $request->validate([
            'name_ar' => 'required|string',
            'name_en' => 'required|string',
            'manager_id' => 'required|exists:users,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
        ]);
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('regions', 'public');
            $validated['image'] = $path;
        }

        $region = Region::create($validated);
        return $this->successResponse($region, 'Region created successfully', 211);
    }
    public function updateRegion(Request $request, Region $region): JsonResponse
    {
        // Handled via standard structural role checking rule inside controllers or custom policy
        if (!auth()->user()->isAdmin()) {
            return $this->errorResponse('Unauthorized operation access boundary profile mismatch', 403);
        }

        $validated = $request->validate([
            'name_ar' => 'sometimes|required|string',
            'name_en' => 'sometimes|required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
        ]);
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('regions', 'public');
            $validated['image'] = $path;
        }
        $region->update($validated);
        return $this->successResponse($region, 'Region updated successfully');
    }

    public function getRegions(): JsonResponse
{
    $user = auth()->user();

    // تبسيط شروط الوصول
    if (!($user->isAdmin() || $user->role === 'client' || $user->role === 'region_manager')) {
        return $this->errorResponse('Access mapping blocked', 403);
    }

    $regions = collect(); 

    if ($user->isAdmin() || $user->role === 'client') {
        $cacheKey = 'all_regions_with_managers';
        $regions = Cache::remember($cacheKey, now()->addDay(), function () {
            return Region::with('manager')->get();
        });
    } elseif ($user->role === 'region_manager') {
        $cacheKey = 'regions_for_manager_' . $user->id;
        $regions = Cache::remember($cacheKey, now()->addDay(), function () use ($user) {
         
            return Region::where('manager_id', $user->id)->get();
        });
    }

    if ($user->isAdmin() || $user->isCompanyManager() || $user->isRegionManager()) {
        return $this->successResponse($regions, 'Regions context retrieved');
    }

    return $this->successResponse(RegionResource::collection($regions), 'Regions context retrieved');
}

    public function showRegion(Region $region): JsonResponse
{
    $user = auth()->user();

    $hasAccess = $user->isAdmin() || $user->role === 'client' || ($user->role === 'region_manager' && $region->manager_id === $user->id);

    if (!$hasAccess) {
        return $this->errorResponse('Access mapping blocked', 403);
    }

    $cacheKey = 'region_' . $region->id . '_details_with_manager_companies';

    $cachedRegion = Cache::remember($cacheKey, now()->addDay(), function () use ($region) {

        $region->load('manager', 'companies');
        return $region;
    });

    if ($user->isAdmin() || $user->isCompanyManager() || $user->isRegionManager()) {
        return $this->successResponse($cachedRegion, 'Region details retrieved');
    }

    return $this->successResponse(new RegionResource($cachedRegion), 'Region details retrieved');
}

    public function deleteRegion(Region $region): JsonResponse
    {
        if (!auth()->user()->isAdmin()) {
            return $this->errorResponse('Only Admins can perform deletion updates', 403);
        }

        $region->delete();
        return $this->successResponse([], 'Region deleted successfully');
    }
    public function getRegionsNames(): JsonResponse
    {
        $regions = Region::all();
        return $this->successResponse(RegionNameResource::collection($regions), 'Regions names retrieved successfully');
    }
    
}
