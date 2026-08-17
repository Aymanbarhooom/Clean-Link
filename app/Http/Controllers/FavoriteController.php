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
        $user = auth()->user();

        $services = Service::whereHas('favoritedBy', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->with(['company.region'])->get();

        $companies = Company::whereHas('favoritedBy', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->with(['region'])->get();
        if ($user->isAdmin() || $user->isCompanyManager() || $user->isRegionManager()) {
            return $this->successResponse([
                'services' => $services,
                'companies' => $companies
            ], 'User favorites catalog retrieved successfully');
        }
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

        $favoriteExists = Favorite::where('user_id', auth()->id())
            ->where('favoritable_id', $targetEntity->id)
            ->where('favoritable_type', $modelType)
            ->first();

        if ($favoriteExists) {
            $favoriteExists->delete();
            return $this->successResponse(['is_favorited' => false], 'Removed from favorites successfully');
        } else {
            Favorite::create([
                'user_id' => auth()->id(),
                'favoritable_id' => $targetEntity->id,
                'favoritable_type' => $modelType,
            ]);
            return $this->successResponse(['is_favorited' => true], 'Added to favorites successfully', 211);
        }
    }
}
