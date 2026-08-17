<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Company;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
class ComplaintController extends Controller
{
    use ApiResponse;
    
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if ($user->role !== 'client') {
            return $this->errorResponse('Only clients are authorized to submit complaints', 403);
        }

        $validated = $request->validate([
            'type' => 'required|in:company,service',
            'id' => 'required|integer',
            'title' => 'required|string|max:255',
            'body' => 'required|string|min:10|max:5000',
        ]);

        $modelClass = $validated['type'] === 'company' ? Company::class : Service::class;
        $complaintableEntity = $modelClass::find($validated['id']);

        if (!$complaintableEntity) {
            return $this->errorResponse('Target entity not found', 404);
        }

        $hasUsed = $this->checkClientUsage($user, $validated['type'], $complaintableEntity);

        if (!$hasUsed) {
            return $this->errorResponse('You can only complain about services/companies you have used', 403);
        }

        $existingComplaint = Complaint::where('client_id', $user->id)
            ->where('complaintable_type', $modelClass)
            ->where('complaintable_id', $complaintableEntity->id)
            ->where('title', $validated['title'])
            ->exists();

        if ($existingComplaint) {
            return $this->errorResponse('You have already submitted a similar complaint about this item', 409);
        }

        $complaint = new Complaint([
            'client_id' => $user->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'is_read' => false,
        ]);

        $complaintableEntity->complaints()->save($complaint);

        return $this->successResponse(
            $complaint->load('client.profile'),
            'Complaint submitted successfully',
            201
        );
    }


    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = Complaint::with(['client.profile', 'complaintable']);

        if ($user->isAdmin()) {
            $query->where('complaintable_type', Company::class);
        }
        elseif ($user->isCompanyManager()) {
            $companyIds = $user->managedCompanies()->pluck('id');

            if ($companyIds->isEmpty()) {
                return $this->successResponse([
                    'companies_complains' => [],
                    'services_complains' => [],
                    'stats' => $this->getComplaintStats($user),
                ], 'No company registered for this manager');
            }

            $query->where('complaintable_type', Service::class)
                ->whereHas('complaintable', function ($q) use ($companyIds) {
                    $q->whereIn('company_id', $companyIds->toArray());
                });
        }
        elseif ($user->role === 'client') {
            $query->where('client_id', $user->id);
        }
        else {
            return $this->errorResponse('Unauthorized to view complaints', 403);
        }

        $this->applyFilters($request, $query);
        $query->orderBy('created_at', 'desc');

        if ($user->role === 'client') {
            $companiesComplains = (clone $query)
                ->where('complaintable_type', Company::class)
                ->get();

            $servicesComplains = (clone $query)
                ->where('complaintable_type', Service::class)
                ->get();

            return $this->successResponse([
                'companies_complains' => $companiesComplains,
                'services_complains' => $servicesComplains,
                'stats' => $this->getComplaintStats($user),
            ], 'Complaints fetched successfully');
        }

        $complaints = $query->get();

        return $this->successResponse([
            'complaints' => $complaints,
            'stats' => $this->getComplaintStats($user),
        ], 'Complaints fetched successfully');
    }

   public function show(Request $request, Complaint $complaint): JsonResponse
    {
        $user = auth()->user();

        if (!$this->canViewComplaint($user, $complaint)) {
            return $this->errorResponse('Unauthorized to view this complaint', 403);
        }

        if ($user->isAdmin() || $user->isCompanyManager()) {
            if (!$complaint->is_read) {
                $complaint->markAsRead();
            }
        }

        $complaint->load([
            'client.profile',
            'complaintable',
        ]);

        if ($user->role === 'client') {
            $complaint->load(['responses' => function ($query) {
                $query->where('is_internal', false)
                    ->with(['responder.profile'])
                    ->orderBy('created_at', 'asc');
            }]);
        } else {
            $complaint->load(['responses' => function ($query) {
                    $query->orderBy('created_at', 'asc');
            }]);
        }

        $responseData = [
            'complaint' => $complaint,
            'statistics' => [
                'total_responses' => $complaint->responses->count(),
                'external_responses' => $complaint->responses->where('is_internal', false)->count(),
                'internal_responses' => $complaint->responses->where('is_internal', true)->count(),
                'has_unresolved' => $complaint->responses->where('is_internal', true)->isNotEmpty(),
            ]
        ];

        return $this->successResponse($responseData, 'Complaint details fetched successfully');
    }

    public function update(Request $request, Complaint $complaint): JsonResponse
    {
        $user = auth()->user();

        if ($complaint->client_id !== $user->id) {
            return $this->errorResponse('You can only update your own complaints', 403);
        }

        if ($complaint->is_read) {
            return $this->errorResponse('This complaint has already been read and cannot be modified', 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'body' => 'sometimes|string|min:10|max:5000',
        ]);

        $complaint->update($validated);

        return $this->successResponse(
            $complaint->load('client.profile'),
            'Complaint updated successfully'
        );
    }

    public function destroy(Request $request, Complaint $complaint): JsonResponse
    {
        $user = auth()->user();

        if ($complaint->client_id !== $user->id && !$user->isAdmin()) {
            return $this->errorResponse('Unauthorized to delete this complaint', 403);
        }

        $complaint->delete();

        return $this->successResponse(null, 'Complaint deleted successfully');
    }

    
    public function markAsRead(Complaint $complaint): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isAdmin() && !$user->isCompanyManager()) {
            return $this->errorResponse('Only admins and company managers can mark complaints as read', 403);
        }

        if ($user->isCompanyManager() && !$this->canManagerAccessComplaint($user, $complaint)) {
            return $this->errorResponse('You do not have access to this complaint', 403);
        }

        $complaint->markAsRead();

        return $this->successResponse(
            $complaint->load('client.profile'),
            'Complaint marked as read'
        );
    }

  
    public function markAsUnread(Complaint $complaint): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isAdmin() && !$user->isCompanyManager()) {
            return $this->errorResponse('Only admins and company managers can mark complaints as unread', 403);
        }

        if ($user->isCompanyManager() && !$this->canManagerAccessComplaint($user, $complaint)) {
            return $this->errorResponse('You do not have access to this complaint', 403);
        }

        $complaint->markAsUnread();

        return $this->successResponse(
            $complaint->load('client.profile'),
            'Complaint marked as unread'
        );
    }

   
    public function unreadCount(): JsonResponse
    {
        $user = auth()->user();
        
        $count = $this->getUnreadComplaintsCount($user);

        return $this->successResponse([
            'unread_count' => $count,
        ], 'Unread complaints count fetched successfully');
    }


    private function checkClientUsage(User $user, string $type, $complaintableEntity): bool
    {
        if ($type === 'company') {
            return Order::where('client_id', $user->id)
                ->whereHas('package.service', function ($query) use ($complaintableEntity) {
                    $query->where('company_id', $complaintableEntity->id);
                })
                ->whereIn('status', ['assigned_to_worker', 'in_process', 'completed'])
                ->exists();
        }

        return Order::where('client_id', $user->id)
            ->whereHas('package.service', function ($query) use ($complaintableEntity) {
                $query->where('id', $complaintableEntity->id);
            })
            ->whereIn('status', ['assigned_to_worker', 'in_process', 'completed'])
            ->exists();
    }

    private function canViewComplaint(User $user, Complaint $complaint): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->role === 'client') {
            return $complaint->client_id === $user->id;
        }

        if ($user->isCompanyManager()) {
            return $this->canManagerAccessComplaint($user, $complaint);
        }

        return false;
    }

    private function canManagerAccessComplaint(User $user, Complaint $complaint): bool
    {
        if ($complaint->complaintable_type !== Service::class) {
            return false;
        }

        $companyIds = $user->managedCompanies()->pluck('id');
        
        return Complaint::where('id', $complaint->id)
            ->whereHas('complaintable', function ($q) use ($companyIds) {
                $q->whereIn('company_id', $companyIds);
            })
            ->exists();
    }

    private function getComplaintStats(User $user): array
    {
        $stats = [];

        if ($user->isAdmin()) {
            $stats = [
                'total' => Complaint::count(),
                'unread' => Complaint::where('is_read', false)->count(),
                'by_type' => [
                    'company' => Complaint::where('complaintable_type', Company::class)->count(),
                    'service' => Complaint::where('complaintable_type', Service::class)->count(),
                ],
            ];
        } 
        elseif ($user->isCompanyManager()) {
            $companyIds = $user->managedCompanies()->pluck('id');
            $stats = [
                'total' => Complaint::where('complaintable_type', Service::class)
                    ->whereHas('complaintable', function ($q) use ($companyIds) {
                        $q->whereIn('company_id', $companyIds);
                    })->count(),
                'unread' => Complaint::where('complaintable_type', Service::class)
                    ->whereHas('complaintable', function ($q) use ($companyIds) {
                        $q->whereIn('company_id', $companyIds);
                    })
                    ->where('is_read', false)
                    ->count(),
            ];
        }
        elseif ($user->role === 'client') {
            $stats = [
                'total' => Complaint::where('client_id', $user->id)->count(),
                'unread' => 0,
            ];
        }

        return $stats;
    }

    private function getUnreadComplaintsCount(User $user): int
    {
        if ($user->isAdmin()) {
            return Complaint::where('complaintable_type', Company::class)
                ->where('is_read', false)
                ->count();
        }

        if ($user->isCompanyManager()) {
            $companyIds = $user->managedCompanies()->pluck('id');
            
            if ($companyIds->isEmpty()) {
                return 0;
            }

            return Complaint::where('complaintable_type', Service::class)
                ->whereHas('complaintable', function ($q) use ($companyIds) {
                    $q->whereIn('company_id', $companyIds);
                })
                ->where('is_read', false)
                ->count();
        }

        return 0;
    }

    private function applyFilters(Request $request, $query): void
    {
        if ($request->has('type')) {
            $type = $request->type;
            if ($type === 'company') {
                $query->where('complaintable_type', Company::class);
            } elseif ($type === 'service') {
                $query->where('complaintable_type', Service::class);
            }
        }

        if ($request->has('status')) {
            if ($request->status === 'read') {
                $query->where('is_read', true);
            } elseif ($request->status === 'unread') {
                $query->where('is_read', false);
            }
        }

        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('body', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $allowedSorts = ['created_at', 'title', 'is_read'];
        
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }
    }

    private function emptyPagination(int $perPage): array
    {
        return [
            'current_page' => 1,
            'per_page' => $perPage,
            'total' => 0,
            'last_page' => 1,
            'from' => null,
            'to' => null,
            'has_more_pages' => false,
        ];
    }

    private function errorResponse(string $message, int $statusCode): JsonResponse
    {
        return response()->json([
            'status' => $statusCode,
            'message' => $message,
            'errors' => [$message],
        ], $statusCode);
    }
}