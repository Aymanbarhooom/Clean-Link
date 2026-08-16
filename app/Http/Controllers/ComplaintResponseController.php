<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\ComplaintResponse;
use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ComplaintResponseController extends Controller
{
    /**
     * Store a new response to a complaint
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Only admins and company managers can respond
        if (!$user->isAdmin() && !$user->isCompanyManager()) {
            return $this->errorResponse('Only admins and company managers can respond to complaints', 403);
        }

        $validated = $request->validate([
            'complaint_id' => 'required|exists:complaints,id',
            'response' => 'required|string|min:3|max:5000',
            'is_internal' => 'boolean',
        ]);

        // Find the complaint
        $complaint = Complaint::find($validated['complaint_id']);
        $client = $complaint->client;

        if (!$complaint) {
            return $this->errorResponse('Complaint not found', 404);
        }

        // Check authorization for company managers
        if ($user->isCompanyManager()) {
            $canRespond = $this->canManagerRespondToComplaint($user, $complaint);

            if (!$canRespond) {
                return $this->errorResponse('You are not authorized to respond to this complaint', 403);
            }
        }

        // Create the response
        $response = new ComplaintResponse([
            'complaint_id' => $complaint->id,
            'responder_id' => $user->id,
            'response' => $validated['response'],
            'is_internal' => $validated['is_internal'] ?? false,
        ]);

        $response->save();

        // Mark complaint as read when responded
        if (!$complaint->is_read) {
            $complaint->markAsRead();
        }

        // Load relationships for response
        $response->load(['responder.profile']);

        if (!$response->is_internal) {
            $responseNotifications = [
            'ar' => [
                'title' => 'تم الرد على شكواك',
                'body' => "تم الرد على شكواك بخصوص الطلب رقم #{$complaint->id}. شكرًا لاستخدامك خدماتنا.",
            ],
            'en' => [
                'title' => 'Response to Your Complaint',
                'body' => "We have responded to your complaint regarding order #{$complaint->id}. Thank you for using our services.",
            ]
        ];
            $notification = $client->notifications()->create([
            'title_ar' => $responseNotifications['ar']['title'],
            'body_ar' => $responseNotifications['ar']['body'],
            'title_en' => $responseNotifications['en']['title'],
            'body_en' => $responseNotifications['en']['body'],
            'is_read' => false,
            'data' => [
                'type' => 'complaint_response',
                'complaint_id' => $complaint->id,
            ]
            ]);
            foreach ($client->fcmTokens as $token) {
                $notificationTitle = $responseNotifications[$token->lang]['title'] ?? $responseNotifications['en']['title'];
                $notificationBody = $responseNotifications[$token->lang]['body'] ?? $responseNotifications['en']['body'];
                app(FirebaseNotificationService::class)->sendPushNotification(
                    $token->token,
                    $notificationTitle,
                    $notificationBody,
                    [
                        'notification_id' => $notification->id,
                        'type' => 'complaint_response',
                        'complaint_id' => $complaint->id,
                    ]
                );
            }
        }

        return $this->successResponse($response, 'Response added successfully', 201);
    }

    /**
     * Get responses for a specific complaint
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        $request->validate([
            'complaint_id' => 'required|exists:complaints,id',
        ]);

        $complaint = Complaint::find($request->complaint_id);

        // Check authorization to view responses
        if (!$this->canViewResponses($user, $complaint)) {
            return $this->errorResponse('Unauthorized to view these responses', 403);
        }

        $perPage = $request->get('per_page', 10);

        // For clients, only show external responses
        if ($user->role === 'client') {
            $responses = $complaint->responses()
                ->where('is_internal', false)
                ->with(['responder.profile'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        } else {
            // Admins and managers see all responses
            $responses = $complaint->responses()
                ->with(['responder.profile'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        }

        $responseData = [
            'data' => $responses->items(),
            'pagination' => [
                'current_page' => $responses->currentPage(),
                'per_page' => $responses->perPage(),
                'total' => $responses->total(),
                'last_page' => $responses->lastPage(),
                'from' => $responses->firstItem(),
                'to' => $responses->lastItem(),
                'has_more_pages' => $responses->hasMorePages(),
            ],
            'complaint' => [
                'id' => $complaint->id,
                'title' => $complaint->title,
                'status' => $complaint->is_read ? 'read' : 'unread',
                'total_responses' => $complaint->responses()->count(),
                'external_responses_count' => $complaint->responses()->where('is_internal', false)->count(),
                'internal_responses_count' => $complaint->responses()->where('is_internal', true)->count(),
            ]
        ];

        return $this->successResponse($responseData, 'Responses fetched successfully');
    }

    /**
     * Update a response (only the responder can update)
     */
    public function update(Request $request, ComplaintResponse $response): JsonResponse
    {
        $user = auth()->user();

        // Only the responder can update their response
        if ($response->responder_id !== $user->id) {
            return $this->errorResponse('You can only update your own responses', 403);
        }

        $validated = $request->validate([
            'response' => 'required|string|min:3|max:5000',
        ]);

        $response->update($validated);

        return $this->successResponse(
            $response->load(['responder.profile']),
            'Response updated successfully'
        );
    }

    /**
     * Delete a response (only responder or admin)
     */
    public function destroy(Request $request, ComplaintResponse $response): JsonResponse
    {
        $user = auth()->user();

        // Only the responder or admin can delete
        if ($response->responder_id !== $user->id && !$user->isAdmin()) {
            return $this->errorResponse('Unauthorized to delete this response', 403);
        }

        $response->delete();

        return $this->successResponse(null, 'Response deleted successfully');
    }

    // ============ Helper Methods ============

    private function canManagerRespondToComplaint(User $user, Complaint $complaint): bool
    {
        // Only can respond to complaints about services
        if ($complaint->complaintable_type !== Service::class) {
            return false;
        }

        // Get the service and check if it belongs to this manager's company
        $service = Service::find($complaint->complaintable_id);

        if (!$service) {
            return false;
        }

        // Check if the service belongs to a company managed by this user
        $companyIds = $user->managedCompanies()->pluck('id');

        return $companyIds->contains($service->company_id);
    }

    private function canViewResponses(User $user, Complaint $complaint): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->role === 'client') {
            return $complaint->client_id === $user->id;
        }

        if ($user->isCompanyManager()) {
            return $this->canManagerRespondToComplaint($user, $complaint);
        }

        return false;
    }

    // ============ Error Response Helper ============
    private function errorResponse(string $message, int $statusCode): JsonResponse
    {
        return response()->json([
            'status' => $statusCode,
            'message' => $message,
            'errors' => [$message],
        ], $statusCode);
    }

    private function successResponse($data, string $message, int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'status' => $statusCode,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }
}
