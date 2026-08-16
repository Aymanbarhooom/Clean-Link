<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

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
        } catch (\UnexpectedValueException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // عند نجاح حجز المبلغ وإمكانية اقتطاعه لاحقاً
        if ($event->type === 'payment_intent.amount_capturable_updated') {
            $paymentIntent = $event->data->object;
            $orderId = $paymentIntent->metadata->order_id ?? null;

            if ($orderId) {
                $order = Order::find($orderId);
                if ($order && $order->payment_method === 'electric') {
                    $order->update([
                        'payment_status' => 'held'
                    ]);
                }
            }
        }

        return response()->json(['status' => 'success'], 200);
    }
}