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
 
        $profile = $worker->workerProfile()->with('user.profile')->first();
        return $this->successResponse($profile, 'Worker extension record structure retrieved');
    }

    /**
     * Modify targeted structural experience attributes.
     */
    public function update(Request $request): JsonResponse
    {
       $worker = auth()->user();
       if(!$worker->isWorker()) {
            return $this->errorResponse('Operational user profile mapping identity is not a worker', 422);
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
