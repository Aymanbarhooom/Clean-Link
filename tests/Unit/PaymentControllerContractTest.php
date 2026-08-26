<?php

namespace Tests\Unit;

use App\Http\Controllers\PaymentController;
use App\Models\Company;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use ReflectionClass;
use Tests\TestCase;

class PaymentControllerContractTest extends TestCase
{
    public function test_client_payment_payload_contains_the_canonical_contract(): void
    {
        $company = new Company(['name_ar' => 'شركة', 'name_en' => 'Clean Co']);
        $company->id = 9;

        $service = new Service(['name_ar' => 'تنظيف', 'name_en' => 'Cleaning']);
        $service->id = 8;
        $service->setRelation('company', $company);

        $package = new Package(['name_ar' => 'أساسية', 'name_en' => 'Standard']);
        $package->id = 7;
        $package->setRelation('service', $service);

        $order = new Order(['status' => 'pending']);
        $order->id = 6;
        $order->start_time = Carbon::parse('2026-08-25 10:00:00');
        $order->setRelation('package', $package);

        $payment = new Payment([
            'order_id' => 6,
            'amount' => 42.50,
            'currency' => 'usd',
            'payment_method' => 'card',
            'payment_status' => 'held',
        ]);
        $payment->id = 5;
        $payment->created_at = Carbon::parse('2026-08-25 09:00:00');
        $payment->setRelation('order', $order);

        $reflection = new ReflectionClass(PaymentController::class);
        $method = $reflection->getMethod('clientPaymentData');
        $payload = $method->invoke(new PaymentController(), $payment);

        $this->assertSame('card', $payload['payment_method']);
        $this->assertSame('held', $payload['payment_status']);
        $this->assertSame(42.5, $payload['amount']);
        $this->assertSame('Cleaning', $payload['service']['name_en']);
        $this->assertSame('Standard', $payload['package']['name_en']);
    }

    public function test_payment_filters_reject_statuses_outside_the_database_enum(): void
    {
        $reflection = new ReflectionClass(PaymentController::class);
        $method = $reflection->getMethod('validatePaymentFilters');

        $this->expectException(ValidationException::class);
        $method->invoke(
            new PaymentController(),
            Request::create('/client/payments', 'GET', ['status' => 'paid'])
        );
    }
}
