<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workgroup;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WorkgroupController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    
    public function index(): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isCompanyManager() && !$user->isAdmin()) {
            return $this->errorResponse('Access restricted to organizational managers', 403);
        }

        $query = Workgroup::with(['leader', 'workers.profile', 'workers.workerProfile.skills']);

        if ($user->isCompanyManager()) {
            $company = $user->managedCompanies()->first();
            if (!$company) return $this->successResponse([], 'No business profile attached');
            $query->where('company_id', $company->id);
        }

        return $this->successResponse($query->get(), 'Workforce groups successfully synchronized');
    }

   
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user->isCompanyManager()) {
            return $this->errorResponse('Only company managers can assemble teams', 403);
        }

        $company = $user->managedCompanies()->first();
        if (!$company) return $this->errorResponse('No active company profile validated', 422);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'leader_id' => 'required|exists:users,id',
            'worker_ids' => 'required|array|min:1',
            'worker_ids.*' => 'required|exists:users,id',
        ]);

        $workgroup = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $company) {
            
            // Build parent record
            $workgroup = Workgroup::create([
                'company_id' => $company->id,
                'name' => $validated['name'],
                'leader_id' => $validated['leader_id'],
            ]);

            // Sync structural staff identities to the many-to-many pivot loop
            // Automatically includes the leader inside the crew membership listing
            $allStaff = array_unique(array_merge([$validated['leader_id']], $validated['worker_ids']));
            $workgroup->workers()->sync($allStaff);

            return $workgroup;
        });

        return $this->successResponse(
            $workgroup->load(['leader', 'workers.profile']), 
            'Crew workgroup established successfully', 
            211
        );
    }

    /**
     * Modify or re-balance existing workgroup structures cleanly.
     * Route: PUT /api/workgroups/{workgroup}
     */
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

    /**
     * Wipe a workgroup from the registry index.
     * Route: DELETE /api/workgroups/{workgroup}
     */
    public function destroy(Workgroup $workgroup): JsonResponse
    {
        if (auth()->user()->id !== $workgroup->company->manager_id) {
            return $this->errorResponse('Unauthorized access control restriction', 403);
        }

        $workgroup->delete();
        return $this->successResponse([], 'Workgroup removed from tracking arrays');
    }
}

