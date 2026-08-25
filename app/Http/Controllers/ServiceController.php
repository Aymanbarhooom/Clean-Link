<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\SkillResource;
use App\Models\Company;
use App\Models\Package;
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
    $user = auth()->user();
    $validated = $request->validate([
        'page' => 'sometimes|integer|min:1',
        'per_page' => 'sometimes|integer|min:1|max:100',
    ]);
    $perPage = $validated['per_page'] ?? 6;

    $query = Service::with(['company.region','category']);

    // Allow conditional filtering by company context if passed by the frontend
    if ($request->has('company_id')) {
        $query->where('company_id', $request->company_id);
    }

    // تطبيق Pagination مع الترتيب حسب التقييم
    $services = $query->orderBy('rating', 'desc')->paginate($perPage);

    // تجهيز الـ Response حسب دور المستخدم
    $responseData = [
        'data' => ($user->isAdmin() || $user->isCompanyManager() || $user->isRegionManager())
            ? $services->items()
            : ServiceResource::collection($services->items()),
        'pagination' => [
            'current_page' => $services->currentPage(),
            'per_page' => $services->perPage(),
            'total' => $services->total(),
            'last_page' => $services->lastPage(),
            'from' => $services->firstItem(),
            'to' => $services->lastItem(),
            'has_more_pages' => $services->hasMorePages(),
        ]
    ];

    return $this->successResponse($responseData, 'Services list successfully synchronized');
}

    /**
     * Return a single service loaded with its custom configurations and metadata.
     * Route: GET /api/services/{id}
     */
    public function show(Service $service): JsonResponse
    {
        $service->load([
            'company',
            'category',
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
     * Return the service's assigned skills with server-side pagination.
     */
    public function skills(Request $request, Service $service): JsonResponse
    {
        $user = auth()->user();

        if ($user->isCompanyManager()) {
            $this->authorize('update', $service);
        }

        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);
        $perPage = $validated['per_page'] ?? 10;

        $skills = $service->requiredSkills()
            ->select(['skills.id', 'skills.name_ar', 'skills.name_en'])
            ->orderBy('skills.id', 'asc')
            ->paginate($perPage);

        $responseData = [
            'data' => SkillResource::collection($skills->items()),
            'pagination' => [
                'current_page' => $skills->currentPage(),
                'per_page' => $skills->perPage(),
                'total' => $skills->total(),
                'last_page' => $skills->lastPage(),
                'from' => $skills->firstItem(),
                'to' => $skills->lastItem(),
                'has_more_pages' => $skills->hasMorePages(),
            ],
        ];

        return $this->successResponse($responseData, 'Service skills fetched successfully');
    }

    /**
     * Atomic endpoint handling concurrent creation of a service and its pricing attributes.
     * Route: POST /api/services
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Service::class);


        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'category_id' => 'required|exists:categories,id',
            'name_ar' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'min_duration' => 'required|integer|min:1',
            'max_duration' => 'required|integer|gte:min_duration',
            'minimum_price' => 'required|numeric|min:0',
            'maximum_price' => 'required|numeric|gte:minimum_price',
            'discount' => 'nullable|numeric|min:0|lte:maximum_price',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',

            // Nested validation matrix for input properties array
            'attributes' => 'nullable|array',
            'attributes.*.id' => 'required|exists:attributes,id',
            'attributes.*.price' => 'required|numeric|min:-100000', // Allows negative tracking offsets if needed
            'attributes.*.duration' => 'required|integer|min:-1440',
        ]);

        $company = Company::find($validated['company_id']);

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

        Package::create(
            [
                'service_id' => $service->id,
                'name_ar' => 'الباقة المفتوحة',
                'name_en' => $service->name_en . 'Open Package',
                'duration' => 0,
                'price' => 0,
                'price_after_discount'  =>0,
                'details_ar' => ['الوصف الأساسي للخدمة'],
                'details_en' => ['Basic service description'],
                'minimum_workers' => 2,
                'is_open_package' => true,
            ]
        );

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
            'minimum_price' => 'sometimes|numeric|min:0',
            'maximum_price' => 'sometimes|numeric|gte:minimum_price',
            'discount' => 'nullable|numeric|min:0|lte:maximum_price',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',

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

    /**
     * Remove one or more required skills from a specific service.
     * Route: DELETE /api/services/{service}/skills
     */
    public function detachSkills(Request $request, Service $service): JsonResponse
    {
        $this->authorize('update', $service);

        $validated = $request->validate([
            'skill_ids' => 'required|array|min:1',
            'skill_ids.*' => 'required|integer|exists:skills,id',
        ]);

        $service->requiredSkills()->detach($validated['skill_ids']);

        return $this->successResponse(
            $service->load('requiredSkills'),
            'Skills removed from the service successfully'
        );
    }

}
