<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    
    public function viewAny(User $user, string $targetRole): bool
    {
        if ($user->isAdmin()) return true;

        if ($user->isCompanyManager() && $targetRole === 'worker') {
            return true;
        }

        return false;
    }

    
    public function create(User $user, string $targetRole): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->role === 'region_manager' && $targetRole === 'company_manager') {
            return true;
        }

        if ($user->isCompanyManager() && $targetRole === 'worker') {
            return true;
        }

        return false;
    }

    
    public function update(User $user, User $targetUser): bool
    {
        if ($user->isAdmin() || $user->role === 'region_manager') {
            return false;
        }

        if ($user->isCompanyManager() && $targetUser->isWorker()) {
            return $user->id === $targetUser->workerProfile?->company?->manager_id;
        }

        return false;
    }

    public function delete(User $user, User $targetUser): bool
    {
        if ($user->isAdmin()) return true;

        if ($user->role === 'region_manager' && $targetUser->isCompanyManager()) {
            return true; 
        }

        return false;
    }
}
