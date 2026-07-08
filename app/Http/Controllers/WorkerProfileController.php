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
        $worker = auth()->user();
        if (!$worker->isWorker()) {
            return $this->errorResponse('Operational user profile mapping identity is not a worker', 422);
        }
        $profile = $worker->workerProfile()->with('user.profile','skills')->first();
        return $this->successResponse($profile, 'Worker extension record structure retrieved');
    }

    public function showOwn(): JsonResponse
    {
        $worker = auth()->user();
        $profile = $worker->workerProfile()->with('user.profile','skills')->first();
        return $this->successResponse($profile, 'Your profile retrieved');
    }

    /**
     * Modify targeted structural experience attributes.
     */
    public function update(Request $request): JsonResponse
    {
        $worker = auth()->user();
        if (!$worker->isWorker()) {
            return $this->errorResponse('Operational user profile mapping identity is not a worker', 422);
        }

        $validated = $request->validate([
            'experience_years' => 'required|integer|min:0',
            'status'=>'required|string|in:active,inactive',
        ]);

        $worker->workerProfile()->update([
            'experience_years' => $validated['experience_years'],
            'status' => $validated['status'],
        ]);

        return $this->successResponse(
            $worker->load(['profile', 'workerProfile']),
            'Worker professional operational metric parameters adjusted'
        );
    }

        /**
     * Attach multiple operational skills to a specific field worker.
     * Route: POST /api/workers/{worker}/skills
     */
    public function attachSkills(Request $request): JsonResponse
    {
        $worker = auth()->user();
        if (!$worker->isWorker()) {
            return $this->errorResponse('Target profile identity is not a registered worker', 422);
        }

        // Verify that the logged-in Company Manager manages this specific worker
        $this->authorize('update', $worker);

        $validated = $request->validate([
            'skill_ids' => 'required|array|min:1',
            'skill_ids.*' => 'required|integer|exists:skills,id',
        ]);

        // Access the workerProfile model extension directly to bridge the pivot table relation mapping
        $worker->workerProfile->skills()->syncWithoutDetaching($validated['skill_ids']);

        return $this->successResponse(
            $worker->load(['workerProfile.skills', 'profile']), 
            'Skills assigned to the worker profile successfully'
        );
    }

}
