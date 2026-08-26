<?php

namespace App\Services\Booking;

use App\Exceptions\BookingConflictException;
use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use App\Models\WorkTime;
use App\Models\Workgroup;
use App\Services\FirebaseNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AtomicBookingService
{
    public function __construct(private readonly FirebaseNotificationService $notifications) {}

    public function book(
        User $client,
        Package $package,
        array $input,
        Carbon $startTime,
        int $serviceDuration,
        int $travelBufferMinutes,
        int $oneWayMinutes,
        float $totalPrice,
        int $minimumWorkers,
        array $attributePivot = [],
    ): array {
        $effectiveEndTime = $startTime->copy()->addMinutes($serviceDuration + $travelBufferMinutes);

        $result = DB::transaction(function () use (
            $client, $package, $input, $startTime, $serviceDuration,
            $travelBufferMinutes, $oneWayMinutes, $totalPrice,
            $minimumWorkers, $attributePivot, $effectiveEndTime,
        ) {
            $package = Package::query()->with('service.company')->lockForUpdate()->findOrFail($package->id);
            $service = $package->service;
            $company = $service->company;

            [$serviceDuration, $totalPrice, $attributePivot] = $this->bookingTerms(
                $package,
                $attributePivot,
            );
            if ($package->is_open_package) {
                $minimumWorkers = max(1, (int) ceil($serviceDuration / 30));
            }
            $effectiveEndTime = $startTime->copy()->addMinutes($serviceDuration + $travelBufferMinutes);

            $this->assertWithinWorkingHours($company->id, $startTime, $effectiveEndTime, $oneWayMinutes);

            // User rows are the stable shared scheduling resource. All company
            // bookings lock them in the same order to minimize deadlocks.
            $workers = User::query()
                ->whereHas('workerProfile', fn ($query) => $query->where('company_id', $company->id))
                ->with(['workerProfile.skills', 'profile'])
                ->orderBy('users.id')
                ->lockForUpdate()
                ->get();

            $requiredSkillIds = $service->requiredSkills()->pluck('skills.id')->map(fn ($id) => (int) $id)->all();
            $eligibleWorkers = $workers->filter(function (User $worker) use ($requiredSkillIds) {
                $profile = $worker->workerProfile;
                if (!$profile || $profile->status === 'off') {
                    return false;
                }

                return $requiredSkillIds === []
                    || $profile->skills->pluck('id')->intersect($requiredSkillIds)->isNotEmpty();
            })->values();

            $selectedWorkers = $this->selectWorkers(
                $eligibleWorkers,
                max(1, $minimumWorkers),
                $requiredSkillIds,
                $startTime,
                $effectiveEndTime,
            );

            if ($selectedWorkers === null) {
                throw new BookingConflictException();
            }

            $paymentStatus = $input['payment_method'] === 'card' ? 'pending' : 'held';
            $order = Order::create([
                'client_id' => $client->id,
                'package_id' => $package->id,
                'location' => $input['location'],
                'latitude' => $input['latitude'],
                'longitude' => $input['longitude'],
                'note' => $input['note'] ?? null,
                'start_time' => $startTime,
                'end_time' => $effectiveEndTime,
                'duration' => $serviceDuration,
                'travel_buffer_minutes' => $travelBufferMinutes,
                'status' => 'assigned_to_worker',
                'total_price' => $totalPrice,
                'payment_method' => $input['payment_method'],
                'payment_status' => $paymentStatus,
                'is_done_with_admin' => $input['payment_method'] === 'card',
                'is_company_paid' => $input['payment_method'] === 'cash',
            ]);

            $order->payments()->create([
                'user_id' => $client->id,
                'amount' => $totalPrice,
                'currency' => 'usd',
                'payment_method' => $input['payment_method'],
                'payment_status' => $paymentStatus,
                'paid_at' => $input['payment_method'] === 'cash' ? now() : null,
            ]);
            $order->calculateAndSetPaymentShares();

            if ($attributePivot !== []) {
                $order->attributes()->attach($attributePivot);
            }

            $leader = $selectedWorkers->sortByDesc(fn (User $worker) => $worker->workerProfile->rating ?? 0)->first();
            $workgroup = Workgroup::create([
                'company_id' => $company->id,
                'name' => 'Auto WG #' . $order->id . ' ' . now()->format('YmdHis'),
                'leader_id' => $leader->id,
            ]);
            $workgroup->workers()->attach($selectedWorkers->pluck('id')->all());
            $order->tasks()->create(['workgroup_id' => $workgroup->id, 'status' => 'pending']);

            return ['order' => $order, 'workers' => $selectedWorkers];
        }, 3);

        // Push delivery is intentionally outside the scheduling transaction.
        $this->notifyWorkers($result['order'], $result['workers']);

        return $result;
    }

    private function assertWithinWorkingHours(int $companyId, Carbon $start, Carbon $effectiveEnd, int $oneWayMinutes): void
    {
        $workTime = WorkTime::query()
            ->where('company_id', $companyId)
            ->where('day_of_week', $start->dayOfWeek)
            ->lockForUpdate()
            ->first();

        if (!$workTime || $workTime->is_holiday || !$workTime->open_at || !$workTime->close_at) {
            throw new BookingConflictException();
        }

        $open = Carbon::parse($start->format('Y-m-d') . ' ' . $workTime->open_at, $start->timezone);
        $close = Carbon::parse($start->format('Y-m-d') . ' ' . $workTime->close_at, $start->timezone);
        $earliest = $open->addMinutes((int) ceil($oneWayMinutes / 30) * 30);

        if ($start->lt($earliest) || $effectiveEnd->gt($close) || !$start->isFuture()) {
            throw new BookingConflictException();
        }
    }

    private function bookingTerms(Package $package, array $attributePivot): array
    {
        $basePrice = (float) ($package->price_after_discount ?? $package->price);
        $duration = (int) $package->duration;

        if (!$package->is_open_package) {
            return [$duration, $basePrice, []];
        }

        $attributes = $package->service->attributes()->get()->keyBy('id');
        $submittedIds = collect(array_keys($attributePivot))->map(fn ($id) => (int) $id)->sort()->values();
        $requiredIds = $attributes->keys()->map(fn ($id) => (int) $id)->sort()->values();
        if ($submittedIds->all() !== $requiredIds->all()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'attributes' => ['Every service attribute must be submitted exactly once.'],
            ]);
        }

        $normalizedPivot = [];
        foreach ($attributes as $attribute) {
            $quantity = (int) ($attributePivot[$attribute->id]['qty'] ?? 0);
            if ($quantity < 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'attributes' => ['Service attribute quantities cannot be negative.'],
                ]);
            }

            $price = (float) $attribute->pivot->price;
            $attributeDuration = (int) $attribute->pivot->duration;
            $basePrice += $price * $quantity;
            $duration += $attributeDuration * $quantity;
            $normalizedPivot[$attribute->id] = [
                'qty' => $quantity,
                'price_at_order' => $price,
            ];
        }

        return [(int) ceil($duration / 30) * 30, $basePrice, $normalizedPivot];
    }

    private function selectWorkers(
        Collection $workers,
        int $minimumWorkers,
        array $requiredSkillIds,
        Carbon $start,
        Carbon $effectiveEnd,
    ): ?Collection {
        foreach ($this->combinations($workers->all(), $minimumWorkers) as $combination) {
            $selected = collect($combination);
            $coveredSkills = $selected
                ->flatMap(fn (User $worker) => $worker->workerProfile->skills->pluck('id'))
                ->unique()
                ->all();

            if (array_diff($requiredSkillIds, $coveredSkills) !== []) {
                continue;
            }

            $hasConflict = $selected->contains(function (User $worker) use ($start, $effectiveEnd) {
                return Order::query()
                    ->where('status', '!=', 'canceled')
                    ->whereHas('tasks.workgroup.workers', fn ($query) => $query->where('users.id', $worker->id))
                    ->where('start_time', '<', $effectiveEnd)
                    ->where('end_time', '>', $start)
                    ->exists();
            });

            if (!$hasConflict) {
                return $selected;
            }
        }

        return null;
    }

    private function combinations(array $items, int $size): array
    {
        if ($size <= 0 || $size > count($items)) {
            return [];
        }
        if ($size === 1) {
            return array_map(fn ($item) => [$item], $items);
        }

        $result = [];
        foreach ($items as $index => $item) {
            foreach ($this->combinations(array_slice($items, $index + 1), $size - 1) as $tail) {
                $result[] = array_merge([$item], $tail);
            }
        }

        return $result;
    }

    private function notifyWorkers(Order $order, Collection $workers): void
    {
        foreach ($workers as $worker) {
            try {
                $notification = $worker->notifications()->create([
                    'title_ar' => 'تم تعيين مهمة جديدة',
                    'body_ar' => "تم تعيين مهمة جديدة لك لطلب رقم #{$order->id}.",
                    'title_en' => 'New Task Assigned',
                    'body_en' => "You have been assigned a new task for Order #{$order->id}.",
                    'data' => ['type' => 'new_task_assigned', 'order_id' => $order->id, 'status' => 'assigned_to_worker'],
                ]);

                foreach ($worker->fcmTokens as $token) {
                    $arabic = $token->lang === 'ar';
                    $this->notifications->sendPushNotification(
                        $token->token,
                        $arabic ? 'تم تعيين مهمة جديدة' : 'New Task Assigned',
                        $arabic ? "تم تعيين مهمة جديدة لك لطلب رقم #{$order->id}." : "You have been assigned a new task for Order #{$order->id}.",
                        ['notification_id' => $notification->id, 'type' => 'new_task_assigned', 'order_id' => $order->id, 'status' => 'assigned_to_worker'],
                    );
                }
            } catch (\Throwable $exception) {
                Log::warning('Booking notification failed.', [
                    'order_id' => $order->id,
                    'worker_id' => $worker->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }
}
