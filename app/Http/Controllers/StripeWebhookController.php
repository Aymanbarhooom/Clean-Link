<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handleWebhook(Request $request): JsonResponse
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature'),
                config('services.stripe.webhook_secret'),
            );
        } catch (\Throwable $exception) {
            Log::warning('Stripe webhook signature validation failed.', ['exception' => $exception->getMessage()]);
            return response()->json(['error' => 'Invalid payload or signature'], 400);
        }

        try {
            $processed = DB::transaction(function () use ($event) {
                $inserted = DB::table('stripe_webhook_events')->insertOrIgnore([
                    'stripe_event_id' => $event->id,
                    'event_type' => $event->type,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                if ($inserted === 0) {
                    return false;
                }

                $object = $event->data->object;
                $intentId = str_starts_with($event->type, 'payment_intent.')
                    ? ($object->id ?? null)
                    : ($object->payment_intent ?? null);
                $paymentId = $object->metadata->payment_id ?? null;
                $orderId = $object->metadata->order_id ?? null;

                $payment = $intentId
                    ? Payment::query()->where('stripe_payment_intent_id', $intentId)->lockForUpdate()->first()
                    : null;
                if (!$payment && $paymentId) {
                    $payment = Payment::query()->lockForUpdate()->find($paymentId);
                }

                $order = $payment?->order()->lockForUpdate()->first();
                if (!$order && $orderId) {
                    $order = Order::query()->lockForUpdate()->find($orderId);
                }
                if (!$order && $intentId) {
                    $order = Order::query()->where('stripe_payment_intent_id', $intentId)->lockForUpdate()->first();
                }

                if ($order && $order->payment_method === 'card') {
                    $payment ??= $order->payments()->latest()->lockForUpdate()->first();
                    if ($intentId && $payment && !$payment->stripe_payment_intent_id) {
                        $payment->stripe_payment_intent_id = $intentId;
                    }

                    $status = $this->paymentStatusForEvent($event->type, $object);
                    if ($status) {
                        $orderData = ['payment_status' => $status];
                        if ($status === 'captured') {
                            $orderData['is_company_paid'] = false;
                        }
                        $order->update($orderData);

                        if ($payment) {
                            $payment->payment_status = $status;
                            if ($status === 'captured') {
                                $payment->paid_at = now();
                            }
                            $payment->save();
                        }
                    }
                } else {
                    Log::notice('Stripe webhook has no matching card order.', [
                        'event_id' => $event->id,
                        'event_type' => $event->type,
                        'payment_intent_id' => $intentId,
                    ]);
                }

                DB::table('stripe_webhook_events')->where('stripe_event_id', $event->id)
                    ->update(['processed_at' => now(), 'updated_at' => now()]);

                return true;
            }, 3);

            return response()->json(['status' => 'success', 'duplicate' => !$processed]);
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    private function paymentStatusForEvent(string $type, object $object): ?string
    {
        return match ($type) {
            'payment_intent.amount_capturable_updated' => 'held',
            'payment_intent.succeeded' => 'captured',
            'payment_intent.payment_failed', 'payment_intent.canceled' => 'failed',
            'charge.succeeded', 'charge.captured' => ($object->captured ?? false) ? 'captured' : 'held',
            'charge.refunded' => 'refunded',
            default => null,
        };
    }
}
