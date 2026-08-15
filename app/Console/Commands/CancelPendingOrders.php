<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Order;
use Carbon\Carbon;

class CancelPendingOrders extends Command
{
    protected $signature = 'orders:cancel-pending';
    protected $description = 'إلغاء الطلبات المعلقة التي مضى عليها ساعة';

    public function handle()
    {
        $cutoffTime = Carbon::now()->subHour();
        $orders = Order::where('status', 'assigned_to_worker')
                       ->where('start_time', '<=', $cutoffTime)
                       ->get();
        foreach ($orders as $order) {
            $order->update(['status' => 'cancelled']);
            // يمكنك إضافة حدث أو إشعار هنا
        }
        $this->info("تم إلغاء {$orders->count()} طلب.");
    }
}