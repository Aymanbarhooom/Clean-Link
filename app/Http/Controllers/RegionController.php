<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegionResource;
use App\Models\Region;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;

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
        
        if ($user->isAdmin() || $user->role === 'client') {
            $regions = Region::with('manager')->get();
        } elseif ($user->role === 'region_manager') {
            $regions = Region::where('manager_id', $user->id)->get();
        } else {
            return $this->errorResponse('Access mapping blocked', 403);
        }

        return $this->successResponse(RegionResource::collection($regions), 'Regions context retrieved');
    }

    public function showRegion(Region $region): JsonResponse
    {
        $user = auth()->user();
        
        if ($user->isAdmin() || $user->role === 'client' || ($user->role === 'region_manager' && $region->manager_id === $user->id)) {
            $region->load('manager', 'companies');
            return $this->successResponse(new RegionResource($region), 'Region details retrieved');
        }

        return $this->errorResponse('Access mapping blocked', 403);
    }

    public function deleteRegion(Region $region): JsonResponse
    {
        if (!auth()->user()->isAdmin()) {
            return $this->errorResponse('Only Admins can perform deletion updates', 403);
        }

        $region->delete();
        return $this->successResponse([], 'Region deleted successfully');
    }
}
