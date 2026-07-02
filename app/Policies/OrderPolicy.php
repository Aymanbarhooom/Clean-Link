<?php

// app/Policies/OrderPolicy.php
namespace App\Policies;

use App\Models\User;
use App\Models\Order;

class OrderPolicy
{


    public function viewAny(User $user): bool
    {
        // يمكن للمستخدمين من نوع Admin عرض جميع الطلبات
        if ($user->isAdmin()) {
            return true;
        }

        // يمكن للعميل عرض طلباته الخاصة
        if ($user->role === 'client') {
            return true; // العميل يمكنه رؤية طلباته الخاصة
        }

        // يمكن لمدير الشركة عرض الطلبات الخاصة بشركته
        if ($user->isCompanyManager()) {
            return true; // مدير الشركة يمكنه رؤية طلبات شركته
        }

        return false; // إذا لم يكن أي من الشروط السابقة متحققًا، يتم رفض الوصول
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


    /**
     * Client cancellation rule constraint.
     */
    public function cancel(User $user, Order $order): bool
    {
        return $user->role === 'client' && $user->id === $order->client_id && $order->status === 'pending';
    }
}
