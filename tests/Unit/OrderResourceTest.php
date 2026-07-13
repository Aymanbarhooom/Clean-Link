<?php

namespace Tests\Unit;

use App\Http\Resources\OrderResource;
use App\Models\Notification;
use App\Models\Order;
use App\Models\User;
use Tests\TestCase;

class OrderResourceTest extends TestCase
{
    public function test_order_resource_includes_loaded_leader(): void
    {
        $order = new Order([
            'id' => 42,
            'client_id' => 1,
            'package_id' => 2,
            'status' => 'assigned_to_worker',
            'location' => 'Test location',
            'start_time' => now(),
            'end_time' => now()->addHour(),
            'duration' => 60,
            'total_price' => 100,
            'note' => null,
        ]);

        $leader = new User([
            'id' => 7,
            'fullname' => 'Leader User',
            'email' => 'leader@example.com',
            'role' => 'worker',
        ]);

        $order->setRelation('leader', $leader);

        $resource = (new OrderResource($order))->resolve();

        $this->assertSame($leader->id, $resource['leader']['id']);
        $this->assertSame($leader->fullname, $resource['leader']['fullname']);
    }

    public function test_notification_model_allows_data_payload_to_be_filled(): void
    {
        $notification = new Notification();

        $notification->fill([
            'user_id' => 1,
            'title_ar' => 'عنوان',
            'body_ar' => 'محتوى',
            'title_en' => 'Title',
            'body_en' => 'Body',
            'data' => ['type' => 'new_task_assigned', 'order_id' => 42],
        ]);

        $this->assertSame(['type' => 'new_task_assigned', 'order_id' => 42], $notification->data);
    }
}
