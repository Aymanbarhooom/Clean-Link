<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CancelElectricPendingOrders extends Command
{
    protected $signature = 'orders:cancel-electric-pending';
    protected $description = 'إلغاء الطلبات الإلكترونية التي لم يؤكد العميل فيها الدفع خلال 10 دقائق';

    public function handle()
    {
        $cutoff = Carbon::now()->subMinutes(10);
        $orders = Order::where('payment_method', 'electric')
            ->where('payment_status', 'pending')
            ->where('created_at', '<=', $cutoff)
            ->get();

        foreach ($orders as $order) {
            try {
                if ($order->stripe_payment_intent_id) {
                    try {
                        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
                        if ($order->payment_status === 'held') {
                            $stripe->paymentIntents->cancel($order->stripe_payment_intent_id);
                            $order->update(['payment_status' => 'refunded']);
                        } elseif (in_array($order->payment_status, ['paid', 'captured'], true)) {
                            $stripe->refunds->create(['payment_intent' => $order->stripe_payment_intent_id]);
                            $order->update(['payment_status' => 'refunded']);
                        }
                    } catch (\Exception $e) {
                        Log::error("Stripe cancel/refund failed for Order #{$order->id}: " . $e->getMessage());
                    }
                }

                $order->update(['status' => 'canceled']);
                $order->tasks()->delete();

                $client = $order->client;
                if ($client) {
                    $client->notifications()->create([
                        'title_ar' => 'تم إلغاء الطلب',
                        'body_ar' => "تم إلغاء طلبك رقم #{$order->id} لعدم تأكيد الدفع خلال المدة المحددة.",
                        'title_en' => 'Order Cancelled',
                        'body_en' => "Your order #{$order->id} was cancelled due to unconfirmed payment.",
                        'is_read' => false,
                        'data' => [
                            'type' => 'order_canceled',
                            'order_id' => $order->id,
                            'status' => 'canceled',
                        ],
                    ]);

                    $notificationId = $client->notifications()->latest()->first()->id ?? null;

                    foreach ($client->fcmTokens as $token) {
                        $title = $token->lang === 'ar' ? 'تم إلغاء الطلب' : 'Order Cancelled';
                        $body = $token->lang === 'ar'
                            ? "تم إلغاء طلبك رقم #{$order->id} لعدم تأكيد الدفع خلال المدة المحددة."
                            : "Your order #{$order->id} was cancelled due to unconfirmed payment.";

                        app(\App\Services\FirebaseNotificationService::class)->sendPushNotification(
                            $token->token,
                            $title,
                            $body,
                            [
                                'notification_id' => $notificationId,
                                'type' => 'order_canceled',
                                'order_id' => $order->id,
                                'status' => 'canceled',
                            ]
                        );
                    }
                }
            } catch (\Exception $e) {
                Log::error("Failed cancelling pending electric order #{$order->id}: " . $e->getMessage());
            }
        }

        $this->info("Checked and cancelled {$orders->count()} electric pending orders.");
    }
}
