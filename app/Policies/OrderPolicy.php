<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Order;

class OrderPolicy
{


    public function viewAny(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->role === 'client') {
            return true; 
        }

        if ($user->isCompanyManager()) {
            return true;
        }

        return false;
    }

    public function view(User $user, Order $order): bool
    {
        if ($user->isAdmin()) return true;
        if ($user->role === 'client') return $user->id === $order->client_id;
        if ($user->isCompanyManager()) return $user->id === $order->package->service->company->manager_id;
        
        return false;
    }

    public function create(User $user): bool
    {
        return $user->role === 'client';
    }


   
    public function cancel(User $user, Order $order): bool
    {
        return $user->role === 'client' && $user->id === $order->client_id && ($order->status === 'pending' || $order->status === 'assigned_to_worker');
    }
}
