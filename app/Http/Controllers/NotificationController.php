<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(): JsonResponse
    {
        $notifications = auth()->user()
            ->notifications()
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse(NotificationResource::collection($notifications), 'Your notifications inbox synchronized successfully');
    }

    public function markAsRead(Notification $notification): JsonResponse
    {
        if ($notification->user_id !== auth()->id()) {
            return $this->errorResponse('Unauthorized access context error', 403);
        }

        $notification->update(['is_read' => true]);

        return $this->successResponse(new NotificationResource($notification), 'Notification marked as read successfully');
    }

    //unread notifications count
    public function unreadCount(): JsonResponse
    {
        $unreadCount = auth()->user()
            ->notifications()
            ->where('is_read', false)
            ->count();
            
        return $this->successResponse(['unread_count' => $unreadCount], 'Unread notifications count fetched successfully');}
}
