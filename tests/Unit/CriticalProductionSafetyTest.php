<?php

namespace Tests\Unit;

use App\Exceptions\BookingConflictException;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Requests\SendChatMessageRequest;
use App\Services\Booking\AtomicBookingService;
use ReflectionClass;
use Tests\TestCase;

class CriticalProductionSafetyTest extends TestCase
{
    public function test_booking_service_keeps_final_check_and_complete_graph_in_one_transaction(): void
    {
        $source = file_get_contents((new ReflectionClass(AtomicBookingService::class))->getFileName());

        $transaction = strpos($source, 'DB::transaction');
        $workerLock = strpos($source, "->orderBy('users.id')", $transaction);
        $conflictCheck = strpos($source, '$this->selectWorkers', $workerLock);
        $orderCreate = strpos($source, 'Order::create', $conflictCheck);
        $taskCreate = strpos($source, "->tasks()->create", $orderCreate);
        $notification = strpos($source, '$this->notifyWorkers', $taskCreate);

        $this->assertNotFalse($transaction);
        $this->assertTrue($transaction < $workerLock);
        $this->assertTrue($workerLock < $conflictCheck);
        $this->assertTrue($conflictCheck < $orderCreate);
        $this->assertTrue($orderCreate < $taskCreate);
        $this->assertTrue($taskCreate < $notification);
        $this->assertStringContainsString("->where('end_time', '>', \$start)", $source);
        $this->assertSame(
            'The selected time is no longer available. Please choose another time.',
            (new BookingConflictException())->getMessage(),
        );
    }

    public function test_webhook_maps_manual_capture_and_terminal_events_to_canonical_statuses(): void
    {
        $controller = new StripeWebhookController();
        $method = (new ReflectionClass($controller))->getMethod('paymentStatusForEvent');

        $this->assertSame('held', $method->invoke($controller, 'payment_intent.amount_capturable_updated', (object) []));
        $this->assertSame('captured', $method->invoke($controller, 'payment_intent.succeeded', (object) []));
        $this->assertSame('failed', $method->invoke($controller, 'payment_intent.payment_failed', (object) []));
        $this->assertSame('refunded', $method->invoke($controller, 'charge.refunded', (object) []));
        $this->assertSame('held', $method->invoke($controller, 'charge.succeeded', (object) ['captured' => false]));
    }

    public function test_chat_conversation_id_validation_does_not_leak_global_existence(): void
    {
        $rules = (new SendChatMessageRequest())->rules();

        $this->assertNotContains('exists:chat_conversations,id', $rules['conversation_id']);
        $this->assertContains('integer', $rules['conversation_id']);
    }

    public function test_owner_scoping_profile_preservation_and_failed_chat_cleanup_are_enforced(): void
    {
        $locationSource = file_get_contents(app_path('Http/Controllers/LocationController.php'));
        $profileSource = file_get_contents(app_path('Http/Controllers/AuthController.php'));
        $chatSource = file_get_contents(app_path('Http/Controllers/ChatController.php'));

        $this->assertSame(3, substr_count($locationSource, "Location::where('user_id', Auth::id())->findOrFail"));
        $this->assertStringContainsString("if (array_key_exists('image', \$validated))", $profileSource);
        $this->assertStringNotContainsString("'image' => \$validated['image'] ?? null", $profileSource);
        $this->assertStringContainsString('deleteFailedEmptyConversation', $chatSource);
        $this->assertStringContainsString('!$conversation->messages()->exists()', $chatSource);
    }

    public function test_open_package_and_stripe_integrity_contracts_are_present(): void
    {
        $orderSource = file_get_contents(app_path('Http/Controllers/OrderController.php'));
        $paymentSource = file_get_contents(app_path('Http/Controllers/PaymentController.php'));
        $webhookSource = file_get_contents(app_path('Http/Controllers/StripeWebhookController.php'));

        $this->assertStringContainsString("'attributes' => 'present|array'", $orderSource);
        $this->assertStringContainsString("'attributes.*.qty' => 'required|integer|min:0'", $orderSource);
        $this->assertStringContainsString('Every service attribute must be submitted exactly once.', $orderSource);
        $this->assertStringContainsString('Use the Open Package booking endpoint', $orderSource);
        $this->assertStringContainsString("'idempotency_key'", $paymentSource);
        $this->assertStringContainsString('stripe_payment_intent_id', $paymentSource);
        $this->assertStringContainsString('insertOrIgnore', $webhookSource);
        $this->assertStringContainsString('$object->payment_intent', $webhookSource);
    }
}
