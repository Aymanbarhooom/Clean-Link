<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class CancelCardPendingOrders extends Command
{
    protected $signature = 'orders:cancel-card-pending';
    protected $description = 'إلغاء طلبات البطاقة التي لم يؤكد العميل فيها الدفع خلال 10 دقائق';

    public function handle(): int
    {
        $cutoff = Carbon::now()->subMinutes(10);
        $orders = Order::where('payment_method', 'card')
            ->where('payment_status', 'pending')
            ->where('status', '!=', 'canceled')
            ->where('created_at', '<=', $cutoff)
            ->get();

        $cancelled = 0;
        $stripe = new StripeClient(config('services.stripe.secret'));

        foreach ($orders as $order) {
            try {
                $payment = $order->payments()->latest()->first();
                $intentId = $payment?->stripe_payment_intent_id ?: $order->stripe_payment_intent_id;

                if ($intentId) {
                    // Stripe calls are deliberately completed before acquiring DB locks.
                    $intent = $stripe->paymentIntents->retrieve($intentId);
                    if ($intent->status === 'requires_capture') {
                        $this->reconcilePayment($order->id, 'held');
                        continue;
                    }
                    if ($intent->status === 'succeeded') {
                        $this->reconcilePayment($order->id, 'captured');
                        continue;
                    }
                    if (in_array($intent->status, ['requires_payment_method', 'requires_confirmation', 'requires_action', 'processing'], true)) {
                        $stripe->paymentIntents->cancel($intentId);
                    } elseif ($intent->status !== 'canceled') {
                        Log::warning('Pending order has an unexpected Stripe status.', ['order_id' => $order->id, 'stripe_status' => $intent->status]);
                        continue;
                    }
                }

                $didCancel = DB::transaction(function () use ($order, $cutoff) {
                    $locked = Order::query()->lockForUpdate()->find($order->id);
                    if (!$locked
                        || $locked->payment_status !== 'pending'
                        || $locked->status === 'canceled'
                        || $locked->created_at->gt($cutoff)) {
                        return false;
                    }

                    $workgroupIds = $locked->tasks()->pluck('workgroup_id');
                    $locked->update(['status' => 'canceled', 'payment_status' => 'failed']);
                    $locked->payments()->latest()->update(['payment_status' => 'failed']);
                    $locked->tasks()->delete();
                    \App\Models\Workgroup::query()->whereIn('id', $workgroupIds)
                        ->whereDoesntHave('tasks')->delete();

                    return true;
                }, 3);

                if (!$didCancel) {
                    continue;
                }
                $cancelled++;

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
            } catch (\Throwable $exception) {
                // Do not report a local cancellation when Stripe state is unknown.
                Log::error('Failed to verify/cancel pending card order.', [
                    'order_id' => $order->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        $this->info("Checked {$orders->count()} pending card orders; cancelled {$cancelled}.");

        return self::SUCCESS;
    }

    private function reconcilePayment(int $orderId, string $status): void
    {
        DB::transaction(function () use ($orderId, $status) {
            $order = Order::query()->lockForUpdate()->find($orderId);
            if (!$order || $order->payment_status !== 'pending') {
                return;
            }

            $order->update(['payment_status' => $status]);
            $order->payments()->latest()->update([
                'payment_status' => $status,
                'paid_at' => $status === 'captured' ? now() : null,
            ]);
        }, 3);
    }
}
