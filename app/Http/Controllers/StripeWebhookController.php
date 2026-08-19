<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handleWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $endpointSecret
            );
        } catch (\Exception $e) {
            Log::error('Stripe Webhook Signature Error: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid payload or signature'], 400);
        }

        $object = $event->data->object;
        $orderId = $object->metadata->order_id ?? null;

        $order = null;
        if ($orderId) {
            $order = Order::find($orderId);
        } elseif (isset($object->id)) {
            $order = Order::where('stripe_payment_intent_id', $object->id)->first();
        }

        if ($order) {
            switch ($event->type) {
                case 'payment_intent.amount_capturable_updated':
                case 'charge.succeeded':
                    if ($order->payment_method === 'electric' && $object->status === 'requires_capture') {
                        $order->update(['payment_status' => 'held']);
                        $order->payments()->latest()->update([
                            'payment_status' => 'held'
                        ]);
                        Log::info("Order #{$order->id} payment status updated to 'held'.");
                    }
                    break;

                case 'payment_intent.succeeded':
                case 'charge.captured':
                    if ($order->payment_method === 'electric') {
                        $order->update([
                            'payment_status' => 'captured',
                            'is_company_paid' => false,
                        ]);
                        Log::info("Order #{$order->id} payment status updated to 'captured'.");
                    }
                    break;

                case 'payment_intent.canceled':
                    $order->update(['payment_status' => 'refunded']);
                    Log::info("Order #{$order->id} payment intent canceled.");
                    break;
            }
        } else {
            Log::warning("Stripe Webhook: Order not found for Event {$event->type}");
        }

        return response()->json(['status' => 'success'], 200);
    }
}
