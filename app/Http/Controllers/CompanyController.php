<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;

class CompanyController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // --- Company Managers Management Section ---

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
        } else { // Region Manager filter context
            $managers = User::where('role', 'company_manager')
                ->whereHas('managedCompanies', function($q) use ($user) {
                    $q->whereHas('region', function($r) use ($user) { $r->where('manager_id', $user->id); });
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

    // --- Company Asset Profiles Section ---

    public function addCompany(Request $request): JsonResponse
    {
        $this->authorize('create', Company::class);
        if($request->hasFile('image')) {
            $path = $request->file('image')->store('company_images', 'public');
            $validated['image'] = $path;
        }
        $validated = $request->validate([
            'manager_id' => 'required|exists:users,id',
            'region_id' => 'required|exists:regions,id',
            'name_ar' => 'required|string',
            'name_en' => 'required|string',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'location_ar' => 'nullable|string',
            'location_en' => 'nullable|string',
            'start_hour' => 'nullable|date_format:H:i',
            'close_hour' => 'nullable|date_format:H:i',
        ]);

        // Enforcement: Ensure Region Manager assigns only within his territory scope
        $user = auth()->user();
        if ($user->role === 'region_manager') {
            $ownsRegion = $user->managedRegions()->where('id', $validated['region_id'])->exists();
            if (!$ownsRegion) return $this->errorResponse('Cannot deploy cross-boundary companies tracking rules', 403);
        }

        $company = Company::create($validated);
        return $this->successResponse($company, 'Company operational profile built', 211);
    }

    public function updateCompany(Request $request, Company $company): JsonResponse
    {
        $this->authorize('update', $company);
        if($request->hasFile('image')) {
            $path = $request->file('image')->store('company_images', 'public');
            $validated['image'] = $path;
        }
        $validated = $request->validate([
            'name_ar' => 'sometimes|string',
            'name_en' => 'sometimes|string',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'location_ar' => 'nullable|string',
            'location_en' => 'nullable|string',
            'is_open' => 'sometimes|boolean',
            'start_hour' => 'nullable|date_format:H:i',
            'close_hour' => 'nullable|date_format:H:i',
        ]);

        $company->update($validated);
        return $this->successResponse($company, 'Company parameters modified successfully');
    }

   public function getCompanies(): JsonResponse 
{ 
    $user = auth()->user(); 
    
    // High-density optimization using relational nested Eager Loading syntax matching clean data schemas 
    $query = Company::with([
        'region.manager'
    ]); 

    if ($user->role === 'region_manager') { 
        $query->whereHas('region', function ($q) use ($user) { 
            $q->where('manager_id', $user->id); 
        }); 
    } elseif ($user->isCompanyManager()) { 
        $query->where('manager_id', $user->id); 
    } 

    // جلب البيانات أولاً من قاعدة البيانات
    $companies = $query->get();

    // تمرير البيانات عبر الـ Resource لضمان تطبيق شروط اللغة والـ whenLoaded
    return $this->successResponse(
        CompanyResource::collection($companies), 
        'Companies list retrieved'
    ); 
} 

    public function showCompany(Company $company): JsonResponse
    {
        $this->authorize('view', $company);
        $company->load(['region', 'services','workers.user.profile', 'reviews.client.profile']); 
        return $this->successResponse(new CompanyResource($company), 'Company profile retrieved');
    }

    public function deleteCompany(Company $company): JsonResponse
    {
        $this->authorize('delete', $company);
        $company->delete();
        return $this->successResponse([], 'Company wiped from registry indices');
    }
}
