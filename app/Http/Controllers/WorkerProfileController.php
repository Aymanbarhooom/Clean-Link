<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\WorkerProfile;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WorkerProfileController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * View structural worker records showing linked users metrics.
     */
    public function show(User $worker): JsonResponse
    {
        if (!$worker->isWorker()) {
            return $this->errorResponse('Target operational user profile mapping identity is not a worker', 422);
        }

        $user = auth()->user();
        
        // Scope structural visibility configurations
        if (!$user->isAdmin() && !$user->isCompanyManager() && $user->id !== $worker->id) {
            return $this->errorResponse('Visibility index query block authorization error', 403);
        }

        $profile = $worker->workerProfile()->with('user.profile')->first();
        return $this->successResponse($profile, 'Worker extension record structure retrieved');
    }

    /**
     * Modify targeted structural experience attributes.
     */
    public function update(Request $request, User $worker): JsonResponse
    {
        if (!$worker->isWorker()) {
            return $this->errorResponse('Target operational reference profile identity is not a worker', 422);
        }

        $user = auth()->user();

        // Security check: Admins or the explicit managing supervisor account can update these metrics
        if (!$user->isAdmin() && !$user->isCompanyManager()) {
            return $this->errorResponse('Action processing boundary execution access denied', 403);
        }

        $validated = $request->validate([
            'experience_years' => 'required|integer|min:0',
        ]);

        $worker->workerProfile()->update([
            'experience_years' => $validated['experience_years']
        ]);

        return $this->successResponse(
            $worker->load(['profile', 'workerProfile']), 
            'Worker professional operational metric parameters adjusted'
        );
    }
}
