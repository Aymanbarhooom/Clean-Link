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

        $tasks = Task::whereHas('workgroup.workers', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })
            ->with(['order.package.service', 'order.client', 'workgroup.leader'])
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

        $order = $task->order;
        $client = $order->client;
        $client->load('fcmTokens');

        if ($task->workgroup->leader_id !== $user->id) {
            return $this->errorResponse('Access Denied. Only the Workgroup Leader can modify task status or upload tracking photos.', 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,on_way,handling,done',
            'image_before' => 'nullable|image|max:2048', 
            'image_after' => 'nullable|image|max:2048',  
        ]);

        if ($request->hasFile('image_before')) {
            $validated['image_before'] = $request->file('image_before')->store('task_images', 'public');
        }
        if ($request->hasFile('image_after')) {
            $validated['image_after'] = $request->file('image_after')->store('task_images', 'public');
        }

        $statusProgression = [
            'pending' => 'on_way',
            'on_way' => 'handling',
            'handling' => 'done',
        ];

        if ($validated['status'] !== $task->status) {
            $expectedStatus = $statusProgression[$task->status] ?? null;

            if ($expectedStatus === null) {
                return $this->errorResponse('This task is already completed and cannot be advanced further.', 422);
            }

            if ($validated['status'] !== $expectedStatus) {
                return $this->errorResponse(
                    "Invalid task status progression. This task can only move from {$task->status} to {$expectedStatus}.",
                    422
                );
            }
        }

        $doneNotifications = [
            'ar' => [
                'title' => 'تم الانتهاء من الطلب',
                'body' => "تم الانتهاء من طلبك رقم #{$order->id}. شكرًا لاستخدامك خدماتنا.",
                'status' => 'مكتملة',
            ],
            'en' => [
                'title' => 'Order Completed',
                'body' => "Your order #{$order->id} has been completed. Thank you for using our services.",
                'status' => 'completed',
            ]
        ];

        $handlingNotifications = [
            'ar' => [
                'title' => 'طلبك قيد المعالجة',
                'body' => "طلبك رقم #{$order->id} قيد المعالجة. شكرًا لاستخدامك خدماتنا.",
                'status' => 'قيد المعالجة',
            ],
            'en' => [
                'title' => 'Order in Process',
                'body' => "Your order #{$order->id} is now in process. Thank you for using our services.",
                'status' => 'in_process',
            ]
        ];

        $onWayNotifications = [
            'ar' => [
                'title' => 'طلبك في الطريق',
                'body' => "طلبك رقم #{$order->id} في الطريق الآن. شكرًا لاستخدامك خدماتنا.",
                'status' => 'في الطريق',
            ],
            'en' => [
                'title' => 'Your Order Is On the Way',
                'body' => "Your order #{$order->id} is on the way. Thank you for using our services.",
                'status' => 'on_way',
            ]
        ];

        if ($validated['status'] === 'done') {
            $task->advanceStatus('done');
            $order->update(['status' => 'completed']);
            $order->update(['payment_status' => 'captured']);

            foreach ($workers as $worker) {
                $worker->workerProfile->status = 'available';
                $worker->workerProfile->save();
            }
            $client->notifications()->create([
                'title_ar' => 'تم الانتهاء من الطلب',
                'body_ar' => "تم الانتهاء من طلبك رقم #{$order->id}. شكرًا لاستخدامك خدماتنا.",
                'title_en' => 'Order Completed',
                'body_en' => "Your order #{$order->id} has been completed. Thank you for using our services.",
                'is_read' => false,
                'data' => [
                    'type' => 'order_status_changed',
                    'order_id' => $order->id,
                    'status' => 'completed',
                ]
            ]);

            foreach ($client->fcmTokens as $token) {
                $notificationTitle = $doneNotifications[$token->lang]['title'] ?? $doneNotifications['en']['title'];
                $notificationBody = $doneNotifications[$token->lang]['body'] ?? $doneNotifications['en']['body'];
                app(FirebaseNotificationService::class)->sendPushNotification(
                    $token->token,
                    $notificationTitle,
                    $notificationBody,
                    [
                        'notification_id' => $client->notifications()->latest()->first()->id,
                        'type' => 'order_status_changed',
                        'order_id' => $order->id,
                        'status' => 'completed',
                    ]
                );
            }
        }

        if ($validated['status'] === 'on_way') {
            if ($order->payment_method === 'electric' && in_array($order->payment_status, ['held', 'paid'], true) && $order->stripe_payment_intent_id) {
                try {
                    $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
                    $stripe->paymentIntents->capture($order->stripe_payment_intent_id);

                    $order->update([
                        'payment_status' => 'captured',
                        'is_company_paid' => false,
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Stripe Capture Failed for Order #' . $order->id . ': ' . $e->getMessage());
                    return $this->errorResponse('Payment processing failed. Please contact support.', 500);
                }
            }

            $task->update([
                'status' => 'on_way',
                'image_before' => $validated['image_before'] ?? $task->image_before,
                'image_after' => $validated['image_after'] ?? $task->image_after,
            ]);

            $client->notifications()->create([
                'title_ar' => 'طلبك في الطريق',
                'body_ar' => "طلبك رقم #{$order->id} في الطريق الآن. شكرًا لاستخدامك خدماتنا.",
                'title_en' => 'Your Order Is On the Way',
                'body_en' => "Your order #{$order->id} is on the way. Thank you for using our services.",
                'is_read' => false,
                'data' => [
                    'type' => 'order_status_changed',
                    'order_id' => $order->id,
                    'status' => 'on_way',
                ]
            ]);

            $order->update(['status' => 'on_way']);

            foreach ($client->fcmTokens as $token) {
                $notificationTitle = $onWayNotifications[$token->lang]['title'] ?? $onWayNotifications['en']['title'];
                $notificationBody = $onWayNotifications[$token->lang]['body'] ?? $onWayNotifications['en']['body'];
                app(FirebaseNotificationService::class)->sendPushNotification(
                    $token->token,
                    $notificationTitle,
                    $notificationBody,
                    [
                        'notification_id' => $client->notifications()->latest()->first()->id,
                        'type' => 'order_status_changed',
                        'order_id' => $order->id,
                        'status' => 'on_way',
                    ]
                );
            }
        }

        if ($validated['status'] === 'handling') {
            $task->update([
                'status' => 'handling',
                'image_before' => $validated['image_before'] ?? $task->image_before,
                'image_after' => $validated['image_after'] ?? $task->image_after,
            ]);

            $client->notifications()->create([
                'title_ar' => 'طلبك قيد المعالجة',
                'body_ar' => "طلبك رقم #{$order->id} قيد المعالجة. شكرًا لاستخدامك خدماتنا.",
                'title_en' => 'Order in Process',
                'body_en' => "Your order #{$order->id} is now in process. Thank you for using our services.",
                'is_read' => false,
                'data' => [
                    'type' => 'order_status_changed',
                    'order_id' => $order->id,
                    'status' => 'in_process',
                ]
            ]);

            $order->update(['status' => 'in_process']);

            foreach ($client->fcmTokens as $token) {
                $notificationTitle = $handlingNotifications[$token->lang]['title'] ?? $handlingNotifications['en']['title'];
                $notificationBody = $handlingNotifications[$token->lang]['body'] ?? $handlingNotifications['en']['body'];
                app(FirebaseNotificationService::class)->sendPushNotification(
                    $token->token,
                    $notificationTitle,
                    $notificationBody,
                    [
                        'notification_id' => $client->notifications()->latest()->first()->id,
                        'type' => 'order_status_changed',
                        'order_id' => $order->id,
                        'status' => 'in_process',
                    ]
                );
            }
        }

        $task->load(['order.package.service', 'workgroup.leader']);

        return $this->successResponse(new TaskResource($task), 'Task progression parameters updated successfully by the leader');
    }
}
