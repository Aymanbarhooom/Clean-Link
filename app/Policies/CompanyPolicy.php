<?php

// app/Policies/CompanyPolicy.php
namespace App\Policies;

use App\Models\User;
use App\Models\Company;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        // Clients see everything, Admin sees everything, Region Manager filters in query loop
        return true; 
    }

    public function view(User $user, Company $company): bool
    {
        if ($user->isAdmin() || $user->role === 'client') return true;
        if ($user->role === 'region_manager') return $user->id === $company->region->manager_id;
        if ($user->isCompanyManager()) return $user->id === $company->manager_id;
        
        return false;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->role === 'region_manager';
    }

    public function update(User $user, Company $company): bool
    {
        // Only the Company Manager can update his own company settings
        return $user->isCompanyManager() && $user->id === $company->manager_id;
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->isAdmin() || ($user->role === 'region_manager' && $user->id === $company->region->manager_id);
    }
}
