<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\PackageResource;
use App\Models\Package;
use App\Models\Service;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PackageController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        // Enforce sanctum token protection for modifications; allow public read lookups
        $this->middleware('auth:sanctum')->except(['index', 'show']);
    }

    /**
     * Display structural package listings based on filtering parent services.
     * Route: GET /api/packages?service_id=5
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'service_id' => 'required|exists:services,id'
        ]);

        $packages = Package::where('service_id', $request->service_id)
            ->orderBy('price', 'asc')
            ->get();
            $user = auth()->user();
        if ($user->isAdmin() || $user->isCompanyManager() || $user->isRegionManager()) {
            return $this->successResponse($packages, 'Service variant packages retrieved successfully');
        }
        return $this->successResponse(PackageResource::collection($packages), 'Service variant packages retrieved successfully');
    }

    /**
     * Fetch explicit specific details for a single target package row.
     * Route: GET /api/packages/{id}
     */
    public function show(Package $package): JsonResponse
    {
        $package->load('service.company', 'service');
        $user = auth()->user();
        if ($user->isAdmin() || $user->isCompanyManager() || $user->isRegionManager()) {
         return $this->successResponse($package, 'Package meta specifications loaded');   
        }
        return $this->successResponse(new PackageResource($package), 'Package meta specifications loaded');
    }

    /**
     * Store a newly created package associated to an existing corporate service.
     * Route: POST /api/packages
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'name_ar' => 'nullable|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'duration' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'price_after_discount' => 'nullable|numeric|min:0',
            'details_ar' => 'nullable|array|min:1',
            'details_ar.*' => 'required|string|max:500',
            'details_en' => 'nullable|array|min:1',
            'details_en.*' => 'required|string|max:500',
        ]);

        $service = Service::find($validated['service_id']);
        
        // Authorize via parent service policy to ensure only the company manager can perform this add operation
        $this->authorize('update', $service);

        $package = Package::create($validated);
        $package->price_after_discount = $service->discount > 0 ? $package->price * (1 - $service->discount / 100) :  $package->price;
        $package->save();

        return $this->successResponse($package, 'New service variant package established successfully', 211);
    }

    /**
     * Update architectural constraints on an existing package row.
     * Route: PUT /api/packages/{id}
     */
    public function update(Request $request, Package $package): JsonResponse
    {
        // Authorize using the parent service company manager authorization gate mapping
        $this->authorize('update', $package->service);

        $validated = $request->validate([
            'name_ar' => 'sometimes|string|max:255',
            'name_en' => 'sometimes|string|max:255',
            'duration' => 'sometimes|integer|min:1',
            'price' => 'sometimes|numeric|min:0',
            'details_ar' => 'sometimes|array|min:1',
            'details_ar.*' => 'required|string|max:500',
            'details_en' => 'sometimes|array|min:1',
            'details_en.*' => 'required|string|max:500',
        ]);
        $service = $package->service;
        $package->update($validated);
        $package->price_after_discount = $service->discount > 0 ? $package->price * (1 - $service->discount / 100) :  $package->price;
        $package->save();

        return $this->successResponse($package, 'Package configuration properties modified successfully');
    }

    /**
     * Purge and remove an explicit package entity option from inventory tracking.
     * Route: DELETE /api/packages/{id}
     */
    public function destroy(Package $package): JsonResponse
    {
        $this->authorize('update', $package->service);

        $package->delete();

        return $this->successResponse([], 'Package variant scrubbed from listing catalogs');
    }
}
