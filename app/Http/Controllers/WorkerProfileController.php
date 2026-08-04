<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\WorkerProfile;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

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
        $profile = $worker->workerProfile()->with('user.profile', 'skills')->first();
        return $this->successResponse($profile, 'Worker extension record structure retrieved');
    }

    public function showOwn(): JsonResponse
    {
        $worker = auth()->user();
        $worker->load(['profile', 'workerProfile.skills']);
        return $this->successResponse($worker, 'Your profile retrieved');
    }

    public function evaluateWorker(Request $request): JsonResponse
{
    // 1. Correct validation syntax
    $validated = $request->validate([
        'worker_id' => 'required|integer|exists:users,id',
        'rating'    => 'nullable|numeric|min:0|max:5',
    ]);

    $manager = auth()->user();
    
    // 2. Get all IDs of companies managed by this user
    $managedCompanyIds = $manager->managedCompanies()->pluck('id')->toArray();

    if (empty($managedCompanyIds)) {
        return $this->errorResponse('You do not manage any companies', 403);
    }

    // 3. Find the profile and ensure it belongs to one of the manager's companies
    $profile = WorkerProfile::where('user_id', $validated['worker_id'])
        ->whereIn('company_id', $managedCompanyIds) // Restricts search to authorized companies
        ->with(['user.profile', 'skills', 'user.workgroups']) // Prevents N+1 database performance hits
        ->first();

    // 4. Secure check: Fail if worker doesn't exist or belongs to another company
    if (!$profile) {
        return $this->errorResponse('Worker profile not found within your managed companies', 422);
    }

    // 5. Explicitly update and save the database record
    if (array_key_exists('rating', $validated)) {
        $profile->rating = $validated['rating'];
        $profile->save();
    }

    return $this->successResponse($profile, 'Worker evaluation updated successfully');
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
            'fullname' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $worker->id,
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('profiles', 'phone')->ignore($worker->profile?->id)],
            'address' => 'nullable|string|max:500',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
            'experience_years' => 'nullable|integer|min:0',
            'status' => 'nullable|string|in:available,off',
        ]);

        // 1. Prepare User Data (فقط إذا كان الحقل يحتوي على قيمة)
        $userData = [];
        if ($request->filled('fullname')) {
            $userData['fullname'] = $validated['fullname'];
        }
        if ($request->filled('email')) {
            $userData['email'] = $validated['email'];
        }

        // 2. Prepare Profile Data
        $profileData = [];
        if ($request->filled('phone')) {
            $profileData['phone'] = $validated['phone'];
        }
        if ($request->filled('address')) {
            $profileData['address'] = $validated['address'];
        }
        if ($request->hasFile('image')) {
            $profileData['image'] = $request->file('image')->store('worker_profiles', 'public');
        }

        // 3. Prepare Worker Profile Data
        $workerProfileData = [];
        if ($request->filled('experience_years')) {
            $workerProfileData['experience_years'] = $validated['experience_years'];
        }
        if ($request->filled('status')) {
            $workerProfileData['status'] = $validated['status'];
        }

        // 4. Perform Updates conditionally
        if (!empty($userData)) {
            $worker->update($userData);
        }
        if (!empty($profileData)) {
            $profile = $worker->profile;
            if (!$profile) {
                $profile = $worker->profile()->create([]);
            }
            $profile->update($profileData);
        }
        if (!empty($workerProfileData)) {
            $worker->workerProfile()->update($workerProfileData);
        }

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

    public function detachSkills(Request $request): JsonResponse
    {
        $worker = auth()->user();
        if (!$worker->isWorker()) {
            return $this->errorResponse('Target profile identity is not a registered worker', 422);
        }

        $validated = $request->validate([
            'skill_ids' => 'required|array|min:1',
            'skill_ids.*' => 'required|integer|exists:skills,id',
        ]);

        // Access the workerProfile model extension directly to bridge the pivot table relation mapping
        $worker->workerProfile->skills()->detach($validated['skill_ids']);

        return $this->successResponse(
            $worker->load(['workerProfile.skills', 'profile']),
            'Skills detached from the worker profile successfully'
        );
    }

    public function updateImage(Request $request): JsonResponse
    {
        $worker = auth()->user();
        if (!$worker->isWorker()) {
            return $this->errorResponse('Target profile identity is not a registered worker', 422);
        }

        $validated = $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,svg|max:2048',
        ]);

         if ($request->hasFile('image')) {
            $path = $request->file('image')->store('profile_images', 'public');
            $validated['image'] = $path;
        }
        $worker->profile()->update([
            'image' => $validated['image'],
        ]);
        return $this->successResponse(
            $worker->load(['profile', 'workerProfile']),
            'Worker profile image updated successfully'
        );
    }
}
