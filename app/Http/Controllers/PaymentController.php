<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\StripeClient;
use App\Traits\ApiResponse;

class PaymentController extends Controller
{
    use ApiResponse;
    public function createPaymentIntent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        $order = Order::find($validated['order_id']);

        /*
        |--------------------------------------------------------------------------
        | Authorization
        |--------------------------------------------------------------------------
        */

        if ($order->client_id !== auth()->id()) {
            return $this->errorResponse(
                'You are not authorized to pay for this order',
                403
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Payment Method
        |--------------------------------------------------------------------------
        */

        if ($order->payment_method !== 'electric') {
            return $this->errorResponse(
                'This order does not use electronic payment',
                422
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Payment Status
        |--------------------------------------------------------------------------
        */

        if ($order->payment_status !== 'pending') {
            return $this->errorResponse(
                'This order is not available for payment',
                422
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Order Status
        |--------------------------------------------------------------------------
        */

        if (in_array($order->status, ['canceled', 'completed', 'in_process'])) {
            return $this->errorResponse(
                'This order cannot be paid',
                422
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Stripe
        |--------------------------------------------------------------------------
        */

        try {
            $stripe = new StripeClient(
                config('services.stripe.secret')
            );

            /*
            |--------------------------------------------------------------------------
            | Convert price to smallest currency unit
            |--------------------------------------------------------------------------
            */

            $amount = (int) round($order->total_price * 100);

            if ($amount <= 0) {
                return $this->errorResponse(
                    'Invalid order amount',
                    422
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Create PaymentIntent
            |--------------------------------------------------------------------------
            */

            $paymentIntent = $stripe->paymentIntents->create([
                'amount' => $amount,
                'currency' => 'usd',
                'payment_method_types' => ['card'],

                'metadata' => [
                    'order_id' => (string) $order->id,
                    'client_id' => (string) $order->client_id,
                    'package_id' => (string) $order->package_id,
                ],
            ]);

            /*
            |--------------------------------------------------------------------------
            | Save Stripe PaymentIntent ID
            |--------------------------------------------------------------------------
            */

            $order->update([
                'stripe_payment_intent_id' => $paymentIntent->id,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Response
            |--------------------------------------------------------------------------
            */

            return $this->successResponse(
                [
                    'order_id' => $order->id,
                    'amount' => $amount,
                    'currency' => 'usd',
                    'payment_intent_id' => $paymentIntent->id,
                    'client_secret' => $paymentIntent->client_secret,
                ],
                'Payment intent created successfully',
                200
            );

        } catch (\Stripe\Exception\ApiErrorException $e) {

            return $this->errorResponse(
                'Unable to create payment intent: ' . $e->getMessage(),
                500
            );

        } catch (\Exception $e) {

            return $this->errorResponse(
                'An unexpected error occurred while creating the payment',
                500
            );
        }
    }
}