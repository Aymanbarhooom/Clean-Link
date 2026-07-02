<?php

namespace Tests\Unit;

use App\Models\User;
use App\Policies\OrderPolicy;
use Tests\TestCase;

class OrderPolicyTest extends TestCase
{
    public function test_client_can_view_their_orders_listing(): void
    {
        $user = new User(['role' => 'client']);
        $policy = new OrderPolicy();

        $this->assertTrue($policy->viewAny($user));
    }

    public function test_company_manager_can_view_company_orders_listing(): void
    {
        $user = new User(['role' => 'company_manager']);
        $policy = new OrderPolicy();

        $this->assertTrue($policy->viewAny($user));
    }

    public function test_worker_cannot_view_orders_listing(): void
    {
        $user = new User(['role' => 'worker']);
        $policy = new OrderPolicy();

        $this->assertFalse($policy->viewAny($user));
    }

    public function test_region_manager_cannot_view_orders_listing(): void
    {
        $user = new User(['role' => 'region_manager']);
        $policy = new OrderPolicy();

        $this->assertFalse($policy->viewAny($user));
    }
}
