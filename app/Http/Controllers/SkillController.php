<?php

namespace App\Http\Controllers;

use App\Http\Resources\SkillResource;
use App\Models\Skill;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        if ($user->isWorker()) {
            $skills = Skill::all();
            return $this->successResponse(SkillResource::collection($skills), 'Skills dictionary fetched successfully');
        }
        $perPage = $request->get('per_page', 6);

        $skills = Skill::orderBy('id', 'asc')->paginate($perPage);

        $responseData = [
            'data' => $skills->items(),
            'pagination' => [
                'current_page' => $skills->currentPage(),
                'per_page' => $skills->perPage(),
                'total' => $skills->total(),
                'last_page' => $skills->lastPage(),
                'from' => $skills->firstItem(),
                'to' => $skills->lastItem(),
                'has_more_pages' => $skills->hasMorePages(),
            ],
        ];

        return $this->successResponse($responseData, 'Skills dictionary fetched successfully');
    }

    public function store(Request $request): JsonResponse
    {
        if (! auth()->user()->isAdmin()) {
            return $this->errorResponse('Access restricted to administrative accounts only', 403);
        }

        $validated = $request->validate([
            'name_ar' => 'required|string|max:255|unique:skills,name_ar',
            'name_en' => 'required|string|max:255|unique:skills,name_en',
        ]);

        $skill = Skill::create($validated);

        return $this->successResponse($skill, 'New skill defined inside system core registry', 211);
    }

    public function destroy(Skill $skill): JsonResponse
    {
        if (! auth()->user()->isAdmin()) {
            return $this->errorResponse('Access restricted to administrative accounts only', 403);
        }

        $skill->delete();

        return $this->successResponse([], 'Skill removed from the system dictionary');
    }
}
