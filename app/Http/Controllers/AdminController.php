<?php

namespace App\Http\Controllers;

use App\Models\AttributeModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\Region;
use App\Models\Service;
use App\Models\Skill;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    use ApiResponse;

    public function searchRegionManagers(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user?->isAdmin()) {
            return $this->errorResponse('Access restricted to administrators only', 403);
        }

        $query = $request->input('query');

        $managers = User::query()
            ->where('role', 'region_manager')
            ->when($query, function ($builder) use ($query) {
                $builder->where(function ($subQuery) use ($query) {
                    $subQuery->where('fullname', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhereRaw('LOWER(fullname) LIKE ?', [strtolower("%{$query}%")]);
                });
            })
            ->orderBy('fullname')
            ->get();

        return $this->successResponse($managers, 'Region managers search completed');
    }

    public function searchRegions(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user?->isAdmin()) {
            return $this->errorResponse('Access restricted to administrators only', 403);
        }

        $query = trim((string) $request->input('query', ''));

        $regions = Region::query()
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($subQuery) use ($query) {
                    $subQuery->where('name_ar', 'like', "%{$query}%")
                        ->orWhere('name_en', 'like', "%{$query}%");
                });
            })
            ->with('manager')
            ->orderBy('name_en')
            ->get();

        return $this->successResponse($regions, 'Regions search completed');
    }

    public function searchSkills(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user?->isAdmin()) {
            return $this->errorResponse('Access restricted to administrators only', 403);
        }

        $query = trim((string) $request->input('query', ''));

        $skills = Skill::query()
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($subQuery) use ($query) {
                    $subQuery->where('name_ar', 'like', "%{$query}%")
                        ->orWhere('name_en', 'like', "%{$query}%");
                });
            })
            ->orderBy('name_en')
            ->get();

        return $this->successResponse($skills, 'Skills search completed');
    }

    public function searchAttributes(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user?->isAdmin()) {
            return $this->errorResponse('Access restricted to administrators only', 403);
        }

        $query = trim((string) $request->input('query', ''));

        $attributes = AttributeModel::query()
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($subQuery) use ($query) {
                    $subQuery->where('name_ar', 'like', "%{$query}%")
                        ->orWhere('name_en', 'like', "%{$query}%");
                });
            })
            ->orderBy('name_en')
            ->get();

        return $this->successResponse($attributes, 'Attributes search completed');
    }

    public function searchCategories(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user?->isAdmin()) {
            return $this->errorResponse('Access restricted to administrators only', 403);
        }

        $query = trim((string) $request->input('query', ''));

        $categories = Category::query()
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($subQuery) use ($query) {
                    $subQuery->where('name_ar', 'like', "%{$query}%")
                        ->orWhere('name_en', 'like', "%{$query}%");
                });
            })
            ->orderBy('name_en')
            ->get();

        return $this->successResponse($categories, 'Categories search completed');
    }

    public function searchCompanies(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = trim((string) $request->input('query', ''));
        $regionId = $request->input('region_id');

        $companies = Company::query()
            ->with(['region.manager'])
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($subQuery) use ($query) {
                    $subQuery->where('name_ar', 'like', "%{$query}%")
                        ->orWhere('name_en', 'like', "%{$query}%");
                });
            })
            ->when($regionId, function ($builder) use ($regionId) {
                $builder->where('region_id', $regionId);
            })
            ->when($user->isRegionManager(), function ($builder) use ($user) {
                $builder->whereHas('region', function ($regionQuery) use ($user) {
                    $regionQuery->where('manager_id', $user->id);
                });
            })
            ->when($user->isCompanyManager(), function ($builder) use ($user) {
                $builder->where('manager_id', $user->id);
            })
            ->orderBy('name_en')
            ->get();

        return $this->successResponse($companies, 'Companies search completed');
    }

    public function searchServices(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = trim((string) $request->input('query', ''));
        $companyId = $request->input('company_id');
        $categoryId = $request->input('category_id');

        $services = Service::query()
            ->with(['company.region', 'category'])
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($subQuery) use ($query) {
                    $subQuery->where('name_ar', 'like', "%{$query}%")
                        ->orWhere('name_en', 'like', "%{$query}%");
                });
            })
            ->when($companyId, function ($builder) use ($companyId) {
                $builder->where('company_id', $companyId);
            })
            ->when($categoryId, function ($builder) use ($categoryId) {
                $builder->where('category_id', $categoryId);
            })
            ->when($user->isRegionManager(), function ($builder) use ($user) {
                $builder->whereHas('company.region', function ($regionQuery) use ($user) {
                    $regionQuery->where('manager_id', $user->id);
                });
            })
            ->when($user->isCompanyManager(), function ($builder) use ($user) {
                $builder->whereHas('company', function ($companyQuery) use ($user) {
                    $companyQuery->where('manager_id', $user->id);
                });
            })
            ->orderBy('name_en')
            ->get();

        return $this->successResponse($services, 'Services search completed');
    }
}
