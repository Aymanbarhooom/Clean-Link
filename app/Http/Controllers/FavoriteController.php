<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\ServiceResource;
use App\Models\Favorite;
use App\Models\Company;
use App\Models\Service;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FavoriteController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

     
    public function index(): JsonResponse
    {
        $userId = auth()->id();

        $services = Service::whereHas('favoritedBy', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->with(['company.region'])->get();

        $companies = Company::whereHas('favoritedBy', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->with(['region'])->get();

        return $this->successResponse([
            'services' => ServiceResource::collection($services),
            'companies' => CompanyResource::collection($companies)
        ], 'User favorites catalog retrieved successfully');
    }

   
    public function toggleFavorite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:company,service',
            'id' => 'required|integer',
        ]);

        $modelType = $validated['type'] === 'company' ? Company::class : Service::class;
        $targetEntity = $modelType::find($validated['id']);

        if (!$targetEntity) {
            return $this->errorResponse('Target favorite entity not found', 404);
        }

        // البحث عن السجل للتأكد إن كان مضافاً مسبقاً أم لا
        $favoriteExists = Favorite::where('user_id', auth()->id())
            ->where('favoritable_id', $targetEntity->id)
            ->where('favoritable_type', $modelType)
            ->first();

        if ($favoriteExists) {
            // إذا كان موجوداً، نقوم بحذفه (Remove from Favorite)
            $favoriteExists->delete();
            return $this->successResponse(['is_favorited' => false], 'Removed from favorites successfully');
        } else {
            // إذا لم يكن موجوداً، نقوم بإنشائه (Add to Favorite)
            Favorite::create([
                'user_id' => auth()->id(),
                'favoritable_id' => $targetEntity->id,
                'favoritable_type' => $modelType,
            ]);
            return $this->successResponse(['is_favorited' => true], 'Added to favorites successfully', 211);
        }
    }
}
