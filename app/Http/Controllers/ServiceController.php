<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
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
        $this->middleware('auth:sanctum')->except(['index', 'show']);
    }

   
    public function index(Request $request): JsonResponse
{
    $user = auth()->user();
    $perPage = $request->get('per_page', 6);
    
    $query = Service::with(['company.region','category']);

    if ($request->has('company_id')) {
        $query->where('company_id', $request->company_id);
    }

    $services = $query->orderBy('rating', 'desc')->paginate($perPage);

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
            'minimum_price' => 'required|numeric|min:0',
            'maximum_price' => 'required|numeric|gte:minimum_price',
            'discount' => 'nullable|numeric|min:0|lte:maximum_price',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
            
            'attributes' => 'nullable|array',
            'attributes.*.id' => 'required|exists:attributes,id',
            'attributes.*.price' => 'required|numeric|min:-100000', 
            'attributes.*.duration' => 'required|integer|min:-1440',
        ]);
        if($request->hasFile('image')) {
            $path = $request->file('image')->store('service_images', 'public');
            $validated['image'] = $path;
        }

        $service = DB::transaction(function () use ($validated, $company) {
            
            $service = $company->services()->create($validated);

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
                'name_ar' => $service->name_ar . 'الباقة الأساسية',
                'name_en' => $service->name_en . 'Basic Package',
                'duration' => 0,
                'price' => 0,
                'price_after_discount'  =>0,
                'details_ar' => ['الوصف الأساسي للخدمة'],
                'details_en' => ['Basic service description'],
                'minimum_workers' => 2
            ]
        );

        return $this->successResponse(
            $service->load('attributes'), 
            'Service framework profile with linked configuration items deployed', 
            211
        );
    }

  
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
            
            $service->update($validated);
            $packages = $service->packages;
            foreach ($packages as $package) {
                $package->price_after_discount = $service->discount > 0 ? $package->price * (1 - $service->discount / 100) :  $package->price;
                $package->save();
            }

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


    public function destroy(Service $service): JsonResponse
    {
        $this->authorize('delete', $service);

        $service->delete();

        return $this->successResponse([], 'Service permanently scrubbed from inventory matrices');
    }
    
   
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

    
    public function attachSkills(Request $request, Service $service): JsonResponse
    {
        $this->authorize('update', $service);

        $validated = $request->validate([
            'skill_ids' => 'required|array|min:1',
            'skill_ids.*' => 'required|integer|exists:skills,id',
        ]);

        $service->requiredSkills()->syncWithoutDetaching($validated['skill_ids']);

        return $this->successResponse(
            $service->load('requiredSkills'), 
            'Skills attached to the service successfully'
        );
    }

    
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

