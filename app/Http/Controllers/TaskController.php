<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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

        return $this->successResponse($tasks, 'Your workgroup tasks logs fetched');
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
         $task->load(['order.package.service', 'workgroup.leader']);
        return $this->successResponse(
          new TaskResource($task),
            'Task details retrieved successfully'
        );
    }

    public function updateStatus(Request $request, Task $task): JsonResponse
    {
        $user = auth()->user();

        // 💥 القفل الأمني الفذ: فحص هل العامل الحالي هو قائد الورشة الفعلي المسند إليها التاسك؟
        if ($task->workgroup->leader_id !== $user->id) {
            return $this->errorResponse('Access Denied. Only the Workgroup Leader can modify task status or upload tracking photos.', 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,on_way,handling,done',
        ]);
        if ($request->hasFile('image_before')) {
            $validated['image_before'] = $request->file('image_before')->store('task_images', 'public');
        }
        if ($request->hasFile('image_after')) {
            $validated['image_after'] = $request->file('image_after')->store('task_images', 'public');
        }

        // تحديث الحقول الممررة بذكاء
        $task->update(array_filter($validated));

        // استدعاء المنهج المساعد لتحديث حالة الـ Order تلقائياً إذا انتهى العمل
        if ($validated['status'] === 'done') {
            $task->advanceStatus('done');
            $order = $task->order;
            $order->update(['status' => 'completed']);
        }

        return $this->successResponse($task->load('order'), 'Task progression parameters updated successfully by the leader');
    }
}
