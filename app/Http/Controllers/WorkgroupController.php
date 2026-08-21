<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Workgroup;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WorkgroupController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isCompanyManager() && !$user->isAdmin()) {
            return $this->errorResponse('Access restricted to organizational managers', 403);
        }

        $query = Workgroup::with(['leader', 'workers.profile', 'workers.workerProfile.skills']);

         if ($request->filled('company_id')) {
            $company = Company::find($request->company_id);

            if (!$company || !$user->canManageCompany($company)) {
                return $this->errorResponse('You do not have permission to view orders for this company', 403);
            }

            $query->where('company_id', $company->id);
        }

        if ($user->isCompanyManager()) {
            $company = $user->managedCompanies()->first();
            if (!$company) return $this->successResponse([], 'No business profile attached');
            $query->where('company_id', $company->id);
        }

        return $this->successResponse($query->get(), 'Workforce groups successfully synchronized');
    }

    public function activeWorkGroups(): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isCompanyManager() && !$user->isAdmin()) {
            return $this->errorResponse('Access restricted to organizational managers', 403);
        }

        $query = Workgroup::with(['leader', 'workers.profile', 'workers.workerProfile.skills'])
            ->whereHas('tasks', function ($taskQuery) {
                $taskQuery->whereIn('status', ['pending', 'on_way', 'handling']);
            });

        if ($user->isCompanyManager()) {
            $company = $user->managedCompanies()->first();
            if (!$company) return $this->successResponse([], 'No business profile attached');
            $query->where('company_id', $company->id);
        }

        return $this->successResponse($query->get(), 'Active workgroups successfully retrieved');
    }

    public function show(Workgroup $workgroup): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isCompanyManager() && !$user->isAdmin()) {
            return $this->errorResponse('Access restricted to organizational managers', 403);
        }

        if ($user->isCompanyManager()) {
            $company = $user->managedCompanies()->first();
            if (!$company) return $this->successResponse([], 'No business profile attached');

            if ($workgroup->company_id !== $company->id) {
                return $this->errorResponse('Unauthorized access to this workgroup', 403);
            }
        }

        $workgroup->load(['leader', 'workers.profile', 'workers.workerProfile.skills', 'tasks.order.package.service']);

        return $this->successResponse($workgroup, 'Workgroup details retrieved successfully');
    }

   
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user->isCompanyManager()) {
            return $this->errorResponse('Only company managers can assemble teams', 403);
        }


        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_id' => 'required|exists:companies,id',
            'leader_id' => 'required|exists:users,id',
            'worker_ids' => 'required|array|min:1',
            'worker_ids.*' => 'required|exists:users,id',
        ]);

        
        $allStaff = array_unique(array_merge([$validated['leader_id']], $validated['worker_ids']));

        $alreadyAssigned = User::whereIn('id', $allStaff)
            ->whereHas('workgroups')
            ->get(['id', 'fullname'])
            ->toArray();

        if (!empty($alreadyAssigned)) {
            return $this->errorResponse(
                'One or more selected users are already assigned to a workgroup',
                422,
                $alreadyAssigned
            );
        }

        $workgroup = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $allStaff) {
            
            $workgroup = Workgroup::create([
                'company_id' => $validated['company_id'],
                'name' => $validated['name'],
                'leader_id' => $validated['leader_id'],
            ]);


            $workgroup->workers()->sync($allStaff);

            return $workgroup;
        });

        return $this->successResponse(
            $workgroup->load(['leader', 'workers.profile']), 
            'Crew workgroup established successfully', 
            211
        );
    }

    public function update(Request $request, Workgroup $workgroup): JsonResponse
    {
        if (auth()->user()->id !== $workgroup->company->manager_id) {
            return $this->errorResponse('Unauthorized company domain mismatch access', 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'leader_id' => 'sometimes|exists:users,id',
            'worker_ids' => 'sometimes|array',
            'worker_ids.*' => 'required|exists:users,id',
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $workgroup) {
            $workgroup->update($validated);

            if (isset($validated['worker_ids']) || isset($validated['leader_id'])) {
                $leaderId = $validated['leader_id'] ?? $workgroup->leader_id;
                $inputWorkers = $validated['worker_ids'] ?? $workgroup->workers()->pluck('users.id')->toArray();
                
                $allStaff = array_unique(array_merge([$leaderId], $inputWorkers));
                $workgroup->workers()->sync($allStaff);
            }
        });

        return $this->successResponse(
            $workgroup->load(['leader', 'workers.profile']), 
            'Workgroup crew re-balanced successfully'
        );
    }

   
    public function destroy(Workgroup $workgroup): JsonResponse
    {
        if (auth()->user()->id !== $workgroup->company->manager_id) {
            return $this->errorResponse('Unauthorized access control restriction', 403);
        }

        $workgroup->delete();
        return $this->successResponse([], 'Workgroup removed from tracking arrays');
    }
}

