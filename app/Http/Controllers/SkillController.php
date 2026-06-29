<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SkillController extends Controller
{
    use ApiResponse;

   
    public function index(): JsonResponse
    {
        $skills = Skill::orderBy('name_en', 'asc')->get();
        return $this->successResponse($skills, 'Skills dictionary fetched successfully');
    }

   
    public function store(Request $request): JsonResponse
    {
        if (!auth()->user()->isAdmin()) {
            return $this->errorResponse('Access restricted to administrative accounts only', 403);
        }

        $validated = $request->validate([
            'name_ar' => 'required|string|max:255|unique:skills,name_ar',
            'name_en' => 'required|string|max:255|unique:skills,name_en',
        ]);

        $skill = Skill::create($validated);

        return $this->successResponse($skill, 'New skill defined inside system core registry', 211);
    }
}
