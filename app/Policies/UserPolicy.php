<?php

// app/Policies/UserPolicy.php
namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine who can view list of users based on roles.
     */
    public function viewAny(User $user, string $targetRole): bool
    {
        if ($user->isAdmin()) return true;

        if ($user->isCompanyManager() && $targetRole === 'worker') {
            return true; // Filtered at Controller query level for his company
        }

        return false;
    }

    /**
     * Creation privileges per requirements.
     */
    public function create(User $user, string $targetRole): bool
    {
        // 1. Admin can add everyone
        if ($user->isAdmin()) {
            return true;
        }

        // 2. Region manager can add company managers only
        if ($user->role === 'region_manager' && $targetRole === 'company_manager') {
            return true;
        }

        // 3. Company manager can add workers only
        if ($user->isCompanyManager() && $targetRole === 'worker') {
            return true;
        }

        return false;
    }

    /**
     * Modification is strictly forbidden for Admins and Region Managers.
     */
    public function update(User $user, User $targetUser): bool
    {
        // Admin and Region Manager cannot update records per your instructions
        if ($user->isAdmin() || $user->role === 'region_manager') {
            return false;
        }

        // Company managers can update workers inside their own company boundary
        if ($user->isCompanyManager() && $targetUser->isWorker()) {
            return $user->id === $targetUser->workerProfile?->company?->manager_id;
        }

        return false;
    }

    /**
     * Deletion rules per requirements.
     */
    public function delete(User $user, User $targetUser): bool
    {
        if ($user->isAdmin()) return true;

        if ($user->role === 'region_manager' && $targetUser->isCompanyManager()) {
            return true; // Validating if manager belongs to his region is managed in the query loop
        }

        return false;
    }
}
