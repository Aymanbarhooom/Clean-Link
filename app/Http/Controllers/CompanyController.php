<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyNameResource;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }


    public function addManager(Request $request): JsonResponse
    {
        $this->authorize('create', [User::class, 'company_manager']);

        $validated = $request->validate([
            'fullname' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
        ]);

        $manager = User::create([
            'fullname' => $validated['fullname'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'company_manager',
        ]);

        $manager->profile()->create();

        return $this->successResponse($manager, 'Company Manager added successfully', 211);
    }

    public function getManagers(): JsonResponse
    {
        $this->authorize('viewAny', [User::class, 'company_manager']);

        $user = auth()->user();
        if ($user->isAdmin()) {
            $managers = User::where('role', 'company_manager')->with('profile')->get();
        } else {
            $managers = User::where('role', 'company_manager')
                ->whereHas('managedCompanies', function ($q) use ($user) {
                    $q->whereHas('region', function ($r) use ($user) {
                        $r->where('manager_id', $user->id); });
                })->with('profile')->get();
        }

        return $this->successResponse($managers, 'Company Managers context index retrieved');
    }

    public function deleteManager(User $manager): JsonResponse
    {
        $this->authorize('delete', $manager);
        $manager->delete();
        return $this->successResponse([], 'Company Manager removed completely');
    }


    public function addCompany(Request $request): JsonResponse
    {
        $this->authorize('create', Company::class);

        $validated = $request->validate([
            'manager_id' => ['required', Rule::exists('users', 'id')->where('role', 'company_manager')],
            'region_id' => 'required|exists:regions,id',
            'name_ar' => 'required|string',
            'name_en' => 'required|string',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'location_ar' => 'nullable|string',
            'location_en' => 'nullable|string',
            'longitude' => 'nullable|numeric|between:-180,180',
            'latitude' => 'nullable|numeric|between:-90,90',
            'image' => 'nullable|image|max:2048'
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('company_images', 'public');
            $validated['image'] = $path;
        }

        $user = auth()->user();
        if ($user->role === 'region_manager') {
            if (!$user->managedRegions()->where('id', $validated['region_id'])->exists()) {
                return $this->errorResponse('Cannot deploy cross-boundary companies tracking rules', 403);
            }
        }

        $company = Company::create($validated);

        $workDays = [];
        for ($day = 0; $day <= 6; $day++) {
            $isHoliday = ($day == 5 || $day == 6);

            $workDays[] = [
                'company_id' => $company->id,
                'day_of_week' => $day,
                'open_at' => $isHoliday ? null : '08:00:00',
                'close_at' => $isHoliday ? null : '16:00:00',
                'is_holiday' => $isHoliday,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        \App\Models\WorkTime::insert($workDays);

        return $this->successResponse($company, 'Company operational profile built', 211);
    }


    public function updateCompany(Request $request, Company $company): JsonResponse
    {
        $this->authorize('update', $company);
        $validated = $request->validate([
            'manager_id' => ['sometimes', Rule::exists('users', 'id')->where('role', 'company_manager')],
            'region_id' => 'sometimes|exists:regions,id',
            'name_ar' => 'sometimes|string',
            'name_en' => 'sometimes|string',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'location_ar' => 'nullable|string',
            'location_en' => 'nullable|string',
            'longitude' => 'nullable|numeric|between:-180,180',
            'latitude' => 'nullable|numeric|between:-90,90',
            'image' => 'nullable|image|max:2048'
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('company_images', 'public');
            $validated['image'] = $path;
        }

        $company->update($validated);
        return $this->successResponse($company, 'Company parameters modified successfully');
    }

   public function getCompanies(Request $request): JsonResponse
{
    $user = auth()->user();
    $perPage = $request->get('per_page', 6);

    $query = Company::with([
        'region.manager',
        'workTimes'
    ]);

    if ($user->role === 'region_manager') {
        $query->whereHas('region', function ($q) use ($user) {
            $q->where('manager_id', $user->id);
        });
    } elseif ($user->isCompanyManager()) {
        $query->where('manager_id', $user->id);
    }

    $companies = $query->orderBy('id', 'asc')->paginate($perPage);

    $responseData = [
        'data' => $user->isAdmin() || $user->isCompanyManager() || $user->isRegionManager() 
            ? $companies->items() 
            : CompanyResource::collection($companies->items()),
        'pagination' => [
            'current_page' => $companies->currentPage(),
            'per_page' => $companies->perPage(),
            'total' => $companies->total(),
            'last_page' => $companies->lastPage(),
            'from' => $companies->firstItem(),
            'to' => $companies->lastItem(),
            'has_more_pages' => $companies->hasMorePages(),
        ]
    ];

    return $this->successResponse($responseData, 'Companies list retrieved');
}

    public function showCompany(Company $company): JsonResponse
    {
        $this->authorize('view', $company);
        $company->load(['manager.profile', 'region.manager', 'services', 'workers.user.profile', 'reviews.client.profile','workTimes']);
        $user = auth()->user();
        if ($user->isAdmin() || $user->isCompanyManager() || $user->isRegionManager()) {
            return $this->successResponse($company, 'Company profile retrieved');
        }
        return $this->successResponse(new CompanyResource($company), 'Company profile retrieved');
    }

    public function deleteCompany(Company $company): JsonResponse
    {
        $this->authorize('delete', $company);
        $company->delete();
        return $this->successResponse([], 'Company wiped from registry indices');
    }

    public function getCompaniesNames(): JsonResponse
    {
        $companies = Company::all();
        return $this->successResponse(CompanyNameResource::collection($companies), 'Companies names retrieved successfully');
    }

    public function adminLocations(Request $request): JsonResponse
    {
        if (!auth()->user()?->isAdmin()) {
            return $this->errorResponse('Access restricted to administrators only', 403);
        }

        $validated = $request->validate([
            'query' => 'nullable|string|max:255',
            'region_id' => 'nullable|integer|exists:regions,id',
        ]);

        $query = trim((string) ($validated['query'] ?? ''));
        $companies = Company::query()
            ->select([
                'id', 'manager_id', 'region_id', 'name_ar', 'name_en',
                'location_ar', 'location_en', 'latitude', 'longitude', 'rating',
            ])
            ->with([
                'region:id,name_ar,name_en',
                'manager:id,fullname,email',
            ])
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($nested) use ($query) {
                    $nested->where('name_ar', 'like', "%{$query}%")
                        ->orWhere('name_en', 'like', "%{$query}%")
                        ->orWhere('location_ar', 'like', "%{$query}%")
                        ->orWhere('location_en', 'like', "%{$query}%");
                });
            })
            ->when($validated['region_id'] ?? null, fn ($builder, $regionId) => $builder->where('region_id', $regionId))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('name_en')
            ->get();

        return $this->successResponse($companies, 'Company map locations retrieved successfully');
    }
}
