<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\Package;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Tests\TestCase;

class OrderPricingTest extends TestCase
{
    public function test_calculate_and_set_total_price_uses_discounted_package_price(): void
    {
        $package = new Package([
            'price' => 200.0,
            'price_after_discount' => 150.0,
        ]);

        $relation = $this->getMockBuilder(BelongsToMany::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();

        $relation->expects($this->once())
            ->method('get')
            ->willReturn(collect());

        $order = $this->getMockBuilder(Order::class)
            ->onlyMethods(['attributes', 'update'])
            ->getMock();

        $order->expects($this->once())
            ->method('attributes')
            ->willReturn($relation);

        $order->expects($this->once())
            ->method('update')
            ->with(['total_price' => 150.0])
            ->willReturn(true);

        $order->setRelation('package', $package);

        $order->calculateAndSetTotalPrice();
    }
}
