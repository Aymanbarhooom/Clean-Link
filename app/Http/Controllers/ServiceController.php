<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class ServiceController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        // Require token verification for modifications, but allow public read permissions
        $this->middleware('auth:sanctum')->except(['index', 'show']);
    }

    /**
     * Display a comprehensive listing of all services.
     * Route: GET /api/services?company_id=1
     */
    public function index(Request $request): JsonResponse
    {
        $query = Service::with(['company.region']);

        // Allow conditional filtering by company context if passed by the frontend
        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        $services = $query->orderBy('rating', 'desc')->get();
        $user = auth()->user();
        if ($user->isAdmin() || $user->isCompanyManager() || $user->isRegionManager()) {
         return $this->successResponse($services, 'Services list successfully synchronized');   
        }
        return $this->successResponse(ServiceResource::collection($services), 'Services list successfully synchronized');
    }

    /**
     * Return a single service loaded with its custom configurations and metadata.
     * Route: GET /api/services/{id}
     */
    public function show(Service $service): JsonResponse
    {
        $service->load([
            'company', 
            'packages', 
            'attributes',
            'reviews.client.profile',
            'images',
            'requiredSkills'
        ]);
        $user = auth()->user();
        if ($user->isAdmin() || $user->isCompanyManager() || $user->isRegionManager()) {
         return $this->successResponse($service, 'Comprehensive service parameters aggregated');
        }
        return $this->successResponse(new ServiceResource($service), 'Comprehensive service parameters aggregated');
    }

    /**
     * Atomic endpoint handling concurrent creation of a service and its pricing attributes.
     * Route: POST /api/services
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Service::class);

        $user = auth()->user();
        $company = $user->managedCompanies()->first();

        if (!$company) {
            return $this->errorResponse('No registered business organization profile linked to your account context', 422);
        }

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'category_id' => 'required|exists:categories,id',
            'name_ar' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'min_duration' => 'required|integer|min:1',
            'max_duration' => 'required|integer|gte:min_duration',
            'price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0|lte:price',
            
            // Nested validation matrix for input properties array
            'attributes' => 'nullable|array',
            'attributes.*.id' => 'required|exists:attributes,id',
            'attributes.*.price' => 'required|numeric|min:-100000', // Allows negative tracking offsets if needed
            'attributes.*.duration' => 'required|integer|min:-1440',
        ]);
        if($request->hasFile('image')) {
            $path = $request->file('image')->store('service_images', 'public');
            $validated['image'] = $path;
        }

        // Wrap execution steps within a database transaction loop to guarantee integrity
        $service = DB::transaction(function () use ($validated, $company) {
            
            // Create base service parameters
            $service = $company->services()->create($validated);

            // Re-map inputs cleanly for sync or attach methods
            if (!empty($validated['attributes'])) {
                $pivotPayload = [];
                foreach ($validated['attributes'] as $attr) {
                    $pivotPayload[$attr['id']] = [
                        'price' => $attr['price'],
                        'duration' => $attr['duration']
                    ];
                }
                $service->attributes()->attach($pivotPayload);
            }

            return $service;
        });

        return $this->successResponse(
            $service->load('attributes'), 
            'Service framework profile with linked configuration items deployed', 
            211
        );
    }

    /**
     * Unified dynamic service modifier updates.
     * Route: PUT /api/services/{id}
     */
    public function update(Request $request, Service $service): JsonResponse
    {
        $this->authorize('update', $service);

        $validated = $request->validate([
            'name_ar' => 'sometimes|string|max:255',
            'name_en' => 'sometimes|string|max:255',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'min_duration' => 'sometimes|integer|min:1',
            'max_duration' => 'sometimes|integer|gte:min_duration',
            'price' => 'sometimes|numeric|min:0',
            'discount' => 'nullable|numeric|min:0|lte:price',
            
            'attributes' => 'nullable|array',
            'attributes.*.id' => 'required|exists:attributes,id',
            'attributes.*.price' => 'required|numeric',
            'attributes.*.duration' => 'required|integer',
        ]);
        if($request->hasFile('image')) {
            $path = $request->file('image')->store('service_images', 'public');
            $validated['image'] = $path;
        }

        DB::transaction(function () use ($validated, $service) {
            
            // Perform target updates on core model attributes
            $service->update($validated);
            $packages = $service->packages;
            foreach ($packages as $package) {
                $package->price_after_discount = $service->discount > 0 ? $package->price * (1 - $service->discount / 100) :  $package->price;
                $package->save();
            }

            // Sync resets and cleans the pivot map, removing omitted records automatically
            if (isset($validated['attributes'])) {
                $pivotPayload = [];
                foreach ($validated['attributes'] as $attr) {
                    $pivotPayload[$attr['id']] = [
                        'price' => $attr['price'],
                        'duration' => $attr['duration']
                    ];
                }
                $service->attributes()->sync($pivotPayload);
            }
        });

        return $this->successResponse(
            $service->load('attributes'), 
            'Service schema architecture mapping parameters updated'
        );
    }

    /**
     * Terminate and delete an operational service.
     * Route: DELETE /api/services/{id}
     */
    public function destroy(Service $service): JsonResponse
    {
        $this->authorize('delete', $service);

        // Deleting the model cleans up related records in the 'attribute_service' table automatically
        $service->delete();

        return $this->successResponse([], 'Service permanently scrubbed from inventory matrices');
    }
    
    /**
     * Update service attributes exclusively, replacing the current list with new one.
     * Route: PATCH /api/services/{id}/attributes
     */
    public function updateAttributes(Request $request, Service $service): JsonResponse
    {
        $this->authorize('update', $service);

        $validated = $request->validate([
            'attributes' => 'required|array',
            'attributes.*.id' => 'required|exists:attributes,id',
            'attributes.*.price' => 'required|numeric',
            'attributes.*.duration' => 'required|integer',
        ]);

        DB::transaction(function () use ($validated, $service) {
            $pivotPayload = [];
            foreach ($validated['attributes'] as $attr) {
                $pivotPayload[$attr['id']] = [
                    'price' => $attr['price'],
                    'duration' => $attr['duration']
                ];
            }
            $service->attributes()->sync($pivotPayload);
        });

        return $this->successResponse(
            $service->load('attributes'),
            'Service attributes list successfully replaced'
        );
    }

        /**
     * Attach multiple competency skills to a specific service.
     * Route: POST /api/services/{service}/skills
     */
    public function attachSkills(Request $request, Service $service): JsonResponse
    {
        // Enforce policy protection ensuring only the managing Company Manager can update this service
        $this->authorize('update', $service);

        $validated = $request->validate([
            'skill_ids' => 'required|array|min:1',
            'skill_ids.*' => 'required|integer|exists:skills,id',
        ]);

        // syncWithoutDetaching prevents duplicate pivot table entries if a skill is re-submitted
        $service->requiredSkills()->syncWithoutDetaching($validated['skill_ids']);

        return $this->successResponse(
            $service->load('requiredSkills'), 
            'Skills attached to the service successfully'
        );
    }
 
}

