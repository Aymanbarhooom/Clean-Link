<?php

// database/seeders/TaskSeeder.php
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
        // 1. Fetch the very first workgroup crew configured in the system indices
        $firstWorkgroup = Workgroup::first();

        if (!$firstWorkgroup) {
            $this->command->error('No workgroups found! Run WorkgroupSeeder before executing TaskSeeder.');
            return;
        }

        // 2. Fetch all orders currently waiting for team assignment
        $pendingOrders = Order::where('status', 'pending')->get();

        if ($pendingOrders->isEmpty()) {
            $this->command->info('No pending orders found to assign.');
            return;
        }

        // 3. Loop and bulk-dispatch every order to the first crew crew team
        foreach ($pendingOrders as $order) {
            
            DB::transaction(function () use ($order, $firstWorkgroup) {
                // Generate the active operational workflow task entry for the collective team
                Task::create([
                    'order_id' => $order->id,
                    'workgroup_id' => $firstWorkgroup->id,
                    'status' => 'on_way', // Crews default straight to 'On the Way' state on dispatch
                    'image_before' => null, // Waiting for field action triggers
                    'image_after' => null,  // Waiting for completion triggers
                ]);

                // Update parent order tracking configuration parameters state 
                $order->update([
                    'status' => 'assigned_to_worker'
                ]);
            });
        }

        $this->command->info('Successfully assigned all pending orders directly to: ' . $firstWorkgroup->name);
    }
}
