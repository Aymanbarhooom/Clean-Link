<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Workgroup;
use App\Models\Task;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        $firstWorkgroup = Workgroup::first();

        if (!$firstWorkgroup) {
            $this->command->error('No workgroups found! Run WorkgroupSeeder before executing TaskSeeder.');
            return;
        }

        $pendingOrders = Order::where('status', 'pending')->get();

        if ($pendingOrders->isEmpty()) {
            $this->command->info('No pending orders found to assign.');
            return;
        }

        foreach ($pendingOrders as $order) {
            
            DB::transaction(function () use ($order, $firstWorkgroup) {
                Task::create([
                    'order_id' => $order->id,
                    'workgroup_id' => $firstWorkgroup->id,
                    'status' => 'pending', 
                    'image_before' => null, 
                    'image_after' => null, 
                ]);

                $order->update([
                    'status' => 'assigned_to_worker'
                ]);
            });
        }

        $this->command->info('Successfully assigned all pending orders directly to: ' . $firstWorkgroup->name);
    }
}
