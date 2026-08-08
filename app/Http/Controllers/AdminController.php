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

    $perPage = $request->get('per_page', 10);
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
        ->paginate($perPage);

    $responseData = [
        'data' => $managers->items(),
        'pagination' => [
            'current_page' => $managers->currentPage(),
            'per_page' => $managers->perPage(),
            'total' => $managers->total(),
            'last_page' => $managers->lastPage(),
            'from' => $managers->firstItem(),
            'to' => $managers->lastItem(),
            'has_more_pages' => $managers->hasMorePages(),
        ]
    ];

    return $this->successResponse($responseData, 'Region managers search completed');
}

public function searchRegions(Request $request): JsonResponse
{
    $user = auth()->user();
    if (!$user?->isAdmin()) {
        return $this->errorResponse('Access restricted to administrators only', 403);
    }

    $perPage = $request->get('per_page', 10);
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
        ->paginate($perPage);

    $responseData = [
        'data' => $regions->items(),
        'pagination' => [
            'current_page' => $regions->currentPage(),
            'per_page' => $regions->perPage(),
            'total' => $regions->total(),
            'last_page' => $regions->lastPage(),
            'from' => $regions->firstItem(),
            'to' => $regions->lastItem(),
            'has_more_pages' => $regions->hasMorePages(),
        ]
    ];

    return $this->successResponse($responseData, 'Regions search completed');
}

public function searchSkills(Request $request): JsonResponse
{
    $user = auth()->user();
    if (!$user?->isAdmin()) {
        return $this->errorResponse('Access restricted to administrators only', 403);
    }

    $perPage = $request->get('per_page', 10);
    $query = trim((string) $request->input('query', ''));

    $skills = Skill::query()
        ->when($query !== '', function ($builder) use ($query) {
            $builder->where(function ($subQuery) use ($query) {
                $subQuery->where('name_ar', 'like', "%{$query}%")
                    ->orWhere('name_en', 'like', "%{$query}%");
            });
        })
        ->orderBy('name_en')
        ->paginate($perPage);

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
        ]
    ];

    return $this->successResponse($responseData, 'Skills search completed');
}

public function searchAttributes(Request $request): JsonResponse
{
    $user = auth()->user();
    if (!$user?->isAdmin()) {
        return $this->errorResponse('Access restricted to administrators only', 403);
    }

    $perPage = $request->get('per_page', 10);
    $query = trim((string) $request->input('query', ''));

    $attributes = AttributeModel::query()
        ->when($query !== '', function ($builder) use ($query) {
            $builder->where(function ($subQuery) use ($query) {
                $subQuery->where('name_ar', 'like', "%{$query}%")
                    ->orWhere('name_en', 'like', "%{$query}%");
            });
        })
        ->orderBy('name_en')
        ->paginate($perPage);

    $responseData = [
        'data' => $attributes->items(),
        'pagination' => [
            'current_page' => $attributes->currentPage(),
            'per_page' => $attributes->perPage(),
            'total' => $attributes->total(),
            'last_page' => $attributes->lastPage(),
            'from' => $attributes->firstItem(),
            'to' => $attributes->lastItem(),
            'has_more_pages' => $attributes->hasMorePages(),
        ]
    ];

    return $this->successResponse($responseData, 'Attributes search completed');
}

public function searchCategories(Request $request): JsonResponse
{
    $user = auth()->user();
    if (!$user?->isAdmin()) {
        return $this->errorResponse('Access restricted to administrators only', 403);
    }

    $perPage = $request->get('per_page', 10);
    $query = trim((string) $request->input('query', ''));

    $categories = Category::query()
        ->when($query !== '', function ($builder) use ($query) {
            $builder->where(function ($subQuery) use ($query) {
                $subQuery->where('name_ar', 'like', "%{$query}%")
                    ->orWhere('name_en', 'like', "%{$query}%");
            });
        })
        ->orderBy('name_en')
        ->paginate($perPage);

    $responseData = [
        'data' => $categories->items(),
        'pagination' => [
            'current_page' => $categories->currentPage(),
            'per_page' => $categories->perPage(),
            'total' => $categories->total(),
            'last_page' => $categories->lastPage(),
            'from' => $categories->firstItem(),
            'to' => $categories->lastItem(),
            'has_more_pages' => $categories->hasMorePages(),
        ]
    ];

    return $this->successResponse($responseData, 'Categories search completed');
}

public function searchCompanies(Request $request): JsonResponse
{
    $user = auth()->user();
    $perPage = $request->get('per_page', 10);
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
        ->paginate($perPage);

    $responseData = [
        'data' => $companies->items(),
        'pagination' => [
            'current_page' => $companies->currentPage(),
            'per_page' => $companies->perPage(),
            'total' => $companies->total(),
            'last_page' => $companies->lastPage(),
            'from' => $companies->firstItem(),
            'to' => $companies->lastItem(),
            'has_more_pages' => $companies->hasMorePages(),
        ]
    ];

    return $this->successResponse($responseData, 'Companies search completed');
}

public function searchServices(Request $request): JsonResponse
{
    $user = auth()->user();
    $perPage = $request->get('per_page', 10);
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
        ->paginate($perPage);

    $responseData = [
        'data' => $services->items(),
        'pagination' => [
            'current_page' => $services->currentPage(),
            'per_page' => $services->perPage(),
            'total' => $services->total(),
            'last_page' => $services->lastPage(),
            'from' => $services->firstItem(),
            'to' => $services->lastItem(),
            'has_more_pages' => $services->hasMorePages(),
        ]
    ];

    return $this->successResponse($responseData, 'Services search completed');
}

    public function getClients(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user?->isAdmin()) {
            return $this->errorResponse('Access restricted to administrators only', 403);
        }

        $query = trim((string) $request->input('query', ''));

        $clients = User::query()
            ->where('role', 'client')
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($subQuery) use ($query) {
                    $subQuery->where('fullname', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%");
                });
            })
            ->with(['profile'])
            ->orderBy('fullname')
            ->get();

        return $this->successResponse($clients, 'Clients fetched successfully');
    }

    public function getWorkers(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user?->isAdmin() && !$user?->isCompanyManager()) {
            return $this->errorResponse('Access restricted to administrators or company managers', 403);
        }

        $query = trim((string) $request->input('query', ''));

        $workers = User::query()
            ->where('role', 'worker')
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($subQuery) use ($query) {
                    $subQuery->where('fullname', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%");
                });
            })
            ->with(['profile', 'workerProfile.company'])
            ->when($user->isCompanyManager(), function ($builder) use ($user) {
                $companyIds = $user->managedCompanies()->pluck('companies.id');
                $builder->whereHas('workerProfile', function ($workerQuery) use ($companyIds) {
                    $workerQuery->whereIn('company_id', $companyIds);
                });
            })
            ->orderBy('fullname')
            ->get();

        return $this->successResponse($workers, 'Workers fetched successfully');
    }

    public function showClient(User $user): JsonResponse
    {
        if (!auth()->user()?->isAdmin()) {
            return $this->errorResponse('Access restricted to administrators only', 403);
        }

        if ($user->role !== 'client') {
            return $this->errorResponse('Requested user is not a client', 404);
        }

        return $this->successResponse($user->load(['profile']), 'Client details fetched successfully');
    }

    public function showWorker(User $user): JsonResponse
    {
        $currentUser = auth()->user();

        if ($currentUser?->isAdmin()) {
            return $this->successResponse($user->load(['profile', 'workerProfile.company']), 'Worker details fetched successfully');
        }

        if (!$currentUser?->isCompanyManager()) {
            return $this->errorResponse('Access restricted to administrators or company managers', 403);
        }

        if ($user->role !== 'worker') {
            return $this->errorResponse('Requested user is not a worker', 404);
        }

        $companyIds = $currentUser->managedCompanies()->pluck('companies.id')->toArray();
        $workerCompanyId = $user->workerProfile?->company_id;

        if (!in_array($workerCompanyId, $companyIds, true)) {
            return $this->errorResponse('Access restricted to workers in your companies', 403);
        }

        return $this->successResponse($user->load(['profile', 'workerProfile.company']), 'Worker details fetched successfully');
    }

    public function deleteUser(User $user): JsonResponse
    {
        $currentUser = auth()->user();

        if ($currentUser?->isAdmin()) {
            $user->delete();
            return $this->successResponse([], 'User deleted successfully');
        }

        if (!$currentUser?->isCompanyManager()) {
            return $this->errorResponse('Access restricted to administrators or company managers', 403);
        }

        if ($user->role !== 'worker') {
            return $this->errorResponse('Company managers can only delete workers', 403);
        }

        $companyIds = $currentUser->managedCompanies()->pluck('companies.id')->toArray();
        $workerCompanyId = $user->workerProfile?->company_id;

        if (!in_array($workerCompanyId, $companyIds, true)) {
            return $this->errorResponse('Access restricted to workers in your companies', 403);
        }

        $user->delete();

        return $this->successResponse([], 'Worker deleted successfully');
    }
}
