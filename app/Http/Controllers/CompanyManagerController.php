<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;

class CompanyManagerController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function addWorker(Request $request): JsonResponse
    {
        $this->authorize('create', [User::class, 'worker']);

        $validated = $request->validate([
            'fullname' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'company_id' => 'required|exists:companies,id',
            'experience_years' => 'required|integer|min:0',
        ]);

        $worker = User::create([
            'fullname' => $validated['fullname'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'worker',
        ]);

        $worker->profile()->create();
        $worker->workerProfile()->create([
            'company_id' => $validated['company_id'],
            'experience_years' => $validated['experience_years'],
            'rating' => 0.00
        ]);

        return $this->successResponse($worker->load('workerProfile'), 'Worker profile built successfully', 211);
    }

    public function getWorkers(Company $company): JsonResponse
    {
        $workers = User::with(['profile', 'workerProfile'])
            ->whereHas('workerProfile', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })
            ->get();

        return $this->successResponse($workers, 'Workers lookup index fetched');
    }



    public function updateWorker(Request $request, User $worker): JsonResponse
    {
        $this->authorize('update', $worker);

        $validated = $request->validate([
            'fullname' => 'sometimes|string',
            'experience_years' => 'sometimes|integer|min:0',
        ]);

        if (isset($validated['fullname'])) {
            $worker->update(['fullname' => $validated['fullname']]);
        }

        if (isset($validated['experience_years'])) {
            $worker->workerProfile()->update(['experience_years' => $validated['experience_years']]);
        }

        return $this->successResponse($worker->load('workerProfile'), 'Worker changes tracked successfully');
    }

    public function deleteWorker(User $worker): JsonResponse
    {
        // Enforces Admin or company boundary controls before removing user identities from tables
        if (!auth()->user()->isAdmin() && auth()->user()->id !== $worker->workerProfile?->company?->manager_id) {
            return $this->errorResponse('Access execution context restricted', 403);
        }

        $worker->delete();
        return $this->successResponse([], 'Worker deleted from operations index');
    }
}
