<?php

// app/Policies/TaskPolicy.php
namespace App\Policies;

use App\Models\User;
use App\Models\Task;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isCompanyManager() || $user->isWorker();
    }

    public function view(User $user, Task $task): bool
    {
        if ($user->isAdmin()) return true;
        if ($user->isWorker()) return $user->id === $task->worker_id;
        if ($user->isCompanyManager()) return $user->id === $task->order->package->service->company->manager_id;

        return false;
    }

    public function update(User $user, Task $task): bool
    {
        // Workers mutate the progression state of the task exclusively
        return $user->isWorker() && $user->id === $task->worker_id;
    }
}
