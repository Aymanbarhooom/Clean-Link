<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Service;

class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return true; 
    }

    public function create(User $user): bool
    {
        return $user->isCompanyManager();
    }

    public function update(User $user, Service $service): bool
    {
        return $user->isCompanyManager() && $user->id === $service->company->manager_id;
    }

    public function delete(User $user, Service $service): bool
    {
        return $user->isCompanyManager() && $user->id === $service->company->manager_id;
    }
}
