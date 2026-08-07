<?php

namespace Tests\Unit;

use App\Http\Controllers\OrderController;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    public function test_calculate_attribute_totals_adds_price_and_rounds_duration_to_next_half_hour(): void
    {
        $controller = new OrderController();
        $method = new \ReflectionMethod($controller, 'calculateAttributeTotals');
        $method->setAccessible(true);

        $firstResult = $method->invoke($controller, [
            ['qty' => 1, 'price' => 10.5, 'duration' => 22],
        ], 100.0, 0);

        $this->assertSame(110.5, $firstResult['total_price']);
        $this->assertSame(30, $firstResult['duration']);

        $secondResult = $method->invoke($controller, [
            ['qty' => 1, 'price' => 5.0, 'duration' => 40],
        ], 100.0, 0);

        $this->assertSame(105.0, $secondResult['total_price']);
        $this->assertSame(60, $secondResult['duration']);
    }
}
