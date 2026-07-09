<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\FirebaseNotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\FirebaseService;


class TaskController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isWorker() && !$user->isAdmin()) {
            return $this->errorResponse('Access restricted to field workers', 403);
        }

        // جلب المهام التي تنتمي لأي ورشة يكون المستخدم الحالي عضواً فيها
        $tasks = Task::whereHas('workgroup.workers', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })
            ->with(['order.package.service', 'workgroup.leader'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse(TaskResource::collection($tasks), 'Your workgroup tasks logs fetched');
    }

    public function show(Task $task): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isWorker() && !$user->isAdmin()) {
            return $this->errorResponse('Access restricted to field workers', 403);
        }

        if (!$user->isAdmin() && !$task->workgroup->workers()->where('users.id', $user->id)->exists()) {
            return $this->errorResponse('Access restricted to task members only', 403);
        }
        $task->load(['order.package.service.company', 'order.client', 'workgroup.leader']);
        return $this->successResponse(
            new TaskResource($task),
            'Task details retrieved successfully'
        );
    }

    public function updateStatus(Request $request, Task $task): JsonResponse
{
    $user = auth()->user();
    $workers = $task->workgroup->workers;

    // 1. FIXED: Correctly load the order's specific client and their FCM tokens
    $order = $task->order;
    $client = $order->client;
    $client->load('fcmTokens'); 

    if ($task->workgroup->leader_id !== $user->id) {
        return $this->errorResponse('Access Denied. Only the Workgroup Leader can modify task status or upload tracking photos.', 403);
    }

    $validated = $request->validate([
        'status' => 'required|in:pending,on_way,handling,done',
        'image_before' => 'nullable|image|max:2048', // 2MB
        'image_after' => 'nullable|image|max:2048', // 2MB
    ]);

    if ($request->hasFile('image_before')) {
        $validated['image_before'] = $request->file('image_before')->store('task_images', 'public');
    }
    if ($request->hasFile('image_after')) {
        $validated['image_after'] = $request->file('image_after')->store('task_images', 'public');
    }

    $task->update(array_filter($validated));

    $doneNotifications = [
        'ar' => [
            'title' => 'تم الانتهاء من الطلب',
            'body' => "تم الانتهاء من طلبك رقم #{$order->id}. شكرًا لاستخدامك خدماتنا.",
        ],
        'en' => [
            'title' => 'Order Completed',
            'body' => "Your order #{$order->id} has been completed. Thank you for using our services.",
        ]
    ];

    $handlingNotifications = [
        'ar' => [
            'title' => 'طلبك قيد المعالجة',
            'body' => "طلبك رقم #{$order->id} قيد المعالجة. شكرًا لاستخدامك خدماتنا.",
        ],
        'en' => [
            'title' => 'Order in Process',
            'body' => "Your order #{$order->id} is now in process. Thank you for using our services.",
        ]
    ];

    if ($validated['status'] === 'done') {
        $task->advanceStatus('done');
        $order->update(['status' => 'completed']);

        foreach ($workers as $worker) {
            $worker->workerProfile->status = 'available';
            $worker->workerProfile->save();
        }

        foreach ($client->fcmTokens as $token) {
            $notificationTitle = $doneNotifications[$token->lang]['title'] ?? $doneNotifications['en']['title'];
            $notificationBody = $doneNotifications[$token->lang]['body'] ?? $doneNotifications['en']['body'];
            app(FirebaseNotificationService::class)->sendPushNotification(
                $token->token,
                $notificationTitle,
                $notificationBody,
                [
                    'order_id' => $order->id,
                    'task_id' => $task->id,
                ]
            );
        }

        $client->notifications()->create([
            'title_ar' => 'تم الانتهاء من الطلب',
            'body_ar' => "تم الانتهاء من طلبك رقم #{$order->id}. شكرًا لاستخدامك خدماتنا.",
            'title_en' => 'Order Completed',
            'body_en' => "Your order #{$order->id} has been completed. Thank you for using our services.",
            'is_read' => false,
        ]);
    }

    if ($validated['status'] === 'handling') {
        $order->update(['status' => 'in_process']);

        foreach ($client->fcmTokens as $token) {
            $notificationTitle = $handlingNotifications[$token->lang]['title'] ?? $handlingNotifications['en']['title'];
            $notificationBody = $handlingNotifications[$token->lang]['body'] ?? $handlingNotifications['en']['body'];
            app(FirebaseNotificationService::class)->sendPushNotification(
                $token->token,
                $notificationTitle,
                $notificationBody,
                [
                    'order_id' => $order->id,
                    'task_id' => $task->id,
                ]
            );
        }

        $client->notifications()->create([
            'title_ar' => 'طلبك قيد المعالجة',
            'body_ar' => "طلبك رقم #{$order->id} قيد المعالجة. شكرًا لاستخدامك خدماتنا.",
            'title_en' => 'Order in Process',
            'body_en' => "Your order #{$order->id} is now in process. Thank you for using our services.",
            'is_read' => false,
        ]);
    }

    $task->load(['order.package.service', 'workgroup.leader']);

    return $this->successResponse(new TaskResource($task), 'Task progression parameters updated successfully by the leader');
}
}
