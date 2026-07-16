<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Package;
use App\Models\Order;
use App\Models\User;
use App\Models\Workgroup;
use App\Services\FirebaseNotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function getAvailableSlots(Package $package): JsonResponse
    {
        $service = $package->service;
        $company = $service->company;
        $packageDuration = $package->duration; // minutes

        $requiredSkillIds = $service->requiredSkills()->pluck('skills.id')->toArray();
        $minimumWorkers = (int) ($package->minimum_workers ?? 1);

        // Fetch company workers who have at least one of the required skills
        $eligibleWorkers = User::whereHas('workerProfile', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        })
            ->whereHas('workerProfile.skills', function ($q) use ($requiredSkillIds) {
                $q->whereIn('skills.id', $requiredSkillIds);
            })
            ->with(['workerProfile.skills'])
            ->get();

        if ($eligibleWorkers->count() < $minimumWorkers) {
            return $this->errorResponse('Not enough eligible workers to satisfy the package minimum', 422);
        }

        // company work times
        $workTimes = $company->workTimes()->get()->keyBy('day_of_week');

        $startDate = Carbon::now();
        $endDate = Carbon::now()->addDays(6);
        $period = CarbonPeriod::create($startDate, $endDate);

        $scheduleMatrix = [];

        foreach ($period as $date) {
            $currentDayKey = $date->format('Y-m-d');
            $dayOfWeek = $date->dayOfWeek;

            $daySetting = $workTimes->get($dayOfWeek);
            if (!$daySetting || $daySetting->is_holiday || !$daySetting->open_at || !$daySetting->close_at) {
                $scheduleMatrix[$currentDayKey] = [];
                continue;
            }

            $openHourStr = Carbon::parse($daySetting->open_at)->format('H:i');
            $closeHourStr = Carbon::parse($daySetting->close_at)->format('H:i');

            $companyOpenTime = Carbon::createFromFormat('Y-m-d H:i', $currentDayKey . ' ' . $openHourStr);
            $companyCloseTime = Carbon::createFromFormat('Y-m-d H:i', $currentDayKey . ' ' . $closeHourStr);

            if ($date->isToday()) {
                $now = Carbon::now();
                $roundedNow = $now->copy();
                if ($now->minute < 30) {
                    $roundedNow->minute(30)->second(0);
                } else {
                    $roundedNow->addHour()->minute(0)->second(0);
                }
                $earliestPossibleStart = $roundedNow->addHour();
                $loopTime = $companyOpenTime->gt($earliestPossibleStart) ? $companyOpenTime : $earliestPossibleStart;
            } else {
                $loopTime = $companyOpenTime;
            }

            $scheduleMatrix[$currentDayKey] = [];

            while ($loopTime->copy()->addMinutes($packageDuration)->lte($companyCloseTime)) {
                $slotStart = $loopTime->copy();
                $slotEnd = $loopTime->copy()->addMinutes($packageDuration);

                $isSlotAvailable = false;

                // generate combinations of workers of size minimumWorkers
                $workerCombinations = $this->combinations($eligibleWorkers->values()->all(), $minimumWorkers);

                foreach ($workerCombinations as $combo) {
                    // combined skills
                    $combined = collect($combo)
                        ->map(fn($u) => $u->workerProfile->skills->pluck('id'))
                        ->flatten()
                        ->unique()
                        ->toArray();

                    if (!empty(array_diff($requiredSkillIds, $combined))) {
                        continue;
                    }

                    // check availability for all workers in combo
                    $conflict = false;
                    foreach ($combo as $worker) {
                        $hasOverlap = Order::where('status', '!=', 'canceled')
                            ->whereHas('tasks.workgroup.workers', function ($q) use ($worker) {
                                $q->where('users.id', $worker->id);
                            })
                            ->where(function ($q) use ($slotStart, $slotEnd) {
                                $q->where('start_time', '<', $slotEnd)
                                    ->where('end_time', '>', $slotStart);
                            })
                            ->exists();

                        if ($hasOverlap) {
                            $conflict = true;
                            break;
                        }
                    }

                    if (!$conflict) {
                        $isSlotAvailable = true;
                        break;
                    }
                }

                if ($isSlotAvailable) {
                    $scheduleMatrix[$currentDayKey][] = $slotStart->format('H:i');
                }

                $loopTime->addMinutes(30);
            }
        }

        return $this->successResponse($scheduleMatrix, 'Dynamic slots mapped across active work-time sheets successfully');
    }

    public function store(Request $request): JsonResponse
    {
        if (auth()->user()->role !== 'client') {
            return $this->errorResponse('Access restricted to registered customer accounts', 403);
        }

        $validated = $request->validate([
            'package_id' => 'required|exists:packages,id',
            'location' => 'required|string|max:500',
            'start_time' => 'required|date|after:now',
            'note' => 'nullable|string|max:1000',

            'attributes' => 'nullable|array',
            'attributes.*.id' => 'required|exists:attributes,id',
            'attributes.*.qty' => 'required|integer|min:1',
        ]);

        $package = Package::with('service.company')->find($validated['package_id']);
        $service = $package->service;

        $startTime = Carbon::parse($validated['start_time']);
        $totalDuration = $package->duration;
        $endTime = $startTime->copy()->addMinutes($totalDuration);

        $order = DB::transaction(function () use ($validated, $package, $service, $startTime, $endTime, $totalDuration) {
            $basePrice = $package->price_after_discount ?? $package->price;

            $order = Order::create([
                'client_id' => auth()->id(),
                'package_id' => $package->id,
                'location' => $validated['location'],
                'note' => $validated['note'] ?? null,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration' => $totalDuration,
                'status' => 'pending',
                'total_price' => $basePrice,
            ]);

            $runningTotalPrice = $basePrice;

            if (!empty($validated['attributes'])) {
                $pivotPayload = [];

                foreach ($validated['attributes'] as $item) {
                    $serviceAttributePivot = $service->attributes()->where('attributes.id', $item['id'])->first();
                    $priceAtOrder = $serviceAttributePivot ? $serviceAttributePivot->pivot->price : 0.00;

                    $pivotPayload[$item['id']] = [
                        'qty' => $item['qty'],
                        'price_at_order' => $priceAtOrder,
                    ];

                    $runningTotalPrice += ($priceAtOrder * $item['qty']);
                }

                $order->attributes()->attach($pivotPayload);
            }

            $order->update(['total_price' => $runningTotalPrice]);

            return $order;
        });

        $order->load(['package.service', 'attributes']);

        // Automatic assignment
        try {
            $service = $package->service;
            $company = $service->company;
            $requiredSkillIds = $service->requiredSkills()->pluck('skills.id')->toArray();
            $minimumWorkers = (int) ($package->minimum_workers ?? 1);

            $eligibleWorkers = User::whereHas('workerProfile', function ($q) use ($company) {
                $q->where('company_id', $company->id);
            })
                ->whereHas('workerProfile.skills', function ($q) use ($requiredSkillIds) {
                    $q->whereIn('skills.id', $requiredSkillIds);
                })
                ->with(['workerProfile.skills', 'profile'])
                ->get();

            if ($eligibleWorkers->count() >= $minimumWorkers) {
                $combinations = $this->combinations($eligibleWorkers->values()->all(), $minimumWorkers);
                $found = null;
                foreach ($combinations as $combo) {
                    $combined = collect($combo)
                        ->map(fn($u) => $u->workerProfile->skills->pluck('id'))
                        ->flatten()
                        ->unique()
                        ->toArray();

                    if (!empty(array_diff($requiredSkillIds, $combined))) {
                        continue;
                    }

                    $conflict = false;
                    foreach ($combo as $worker) {
                        $hasOverlap = Order::where('status', '!=', 'canceled')
                            ->whereHas('tasks.workgroup.workers', function ($q) use ($worker) {
                                $q->where('users.id', $worker->id);
                            })
                            ->where(function ($q) use ($startTime, $endTime) {
                                $q->where('start_time', '<', $endTime)
                                    ->where('end_time', '>', $startTime);
                            })
                            ->exists();

                        if ($hasOverlap) {
                            $conflict = true;
                            break;
                        }
                    }

                    if (!$conflict) {
                        $found = $combo;
                        break;
                    }
                }

                if ($found) {
                    $leader = collect($found)->sortByDesc(fn($u) => $u->workerProfile->rating ?? 0)->first();
                    $order->setRelation('leader', $leader);

                    $workgroup = Workgroup::create([
                        'company_id' => $company->id,
                        'name' => 'Auto WG #' . $order->id . ' ' . now()->format('YmdHis'),
                        'leader_id' => $leader->id,
                    ]);

                    $workerIds = collect($found)->pluck('id')->toArray();
                    $workgroup->workers()->attach($workerIds);

                    DB::transaction(function () use ($order, $workgroup) {
                        $order->tasks()->create([
                            'workgroup_id' => $workgroup->id,
                            'status' => 'pending'
                        ]);
                        $order->update(['status' => 'assigned_to_worker']);
                    });
                    $newTaskNotifications = [
                        'ar' => [
                            'title' => 'مهمة جديدة تم تعيينها',
                            'body' => "تم تعيين مهمة جديدة لك لطلب رقم #{$order->id}. يرجى التحقق من لوحة التحكم الخاصة بك لمزيد من التفاصيل.",
                            'status' => 'قيد المعالجة',
                        ],
                        'en' => [
                            'title' => 'New Task Assigned',
                            'body' => "You have been assigned a new task for Order #{$order->id}. Please check your dashboard for details.",
                            'status' => 'in_process',
                        ]
                    ];

                    foreach ($found as $worker) {
                        $notification = $worker->notifications()->create([
                            'title_ar' => 'تم تعيين مهمة جديدة',
                            'body_ar' => "تم تعيين مهمة جديدة لك لطلب رقم #{$order->id}. يرجى التحقق من لوحة التحكم الخاصة بك لمزيد من التفاصيل.",
                            'title_en' => 'New Task Assigned',
                            'body_en' => "You have been assigned a new task for Order #{$order->id}. Please check your dashboard for details.",
                            'data' => [
                                'type' => 'new_task_assigned',
                                'order_id' => $order->id,
                                'status' => 'assigned_to_worker',
                            ],
                        ]);

                        foreach ($worker->fcmTokens as $token) {
                            $notificationTitle = $newTaskNotifications[$token->lang]['title'] ?? $newTaskNotifications['en']['title'];
                            $notificationBody = $newTaskNotifications[$token->lang]['body'] ?? $newTaskNotifications['en']['body'];
                            app(FirebaseNotificationService::class)->sendPushNotification(
                                $token->token,
                                $notificationTitle,
                                $notificationBody,
                                [
                                    'notification_id' => $notification->id,
                                    'type' => 'new_task_assigned',
                                    'order_id' => $order->id,
                                    'status' => 'assigned_to_worker',
                                ]
                            );
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // proceed quietly; order stays pending if assignment fails
        }

        return $this->successResponse(
            new OrderResource($order),
            'Booking submitted and placed under review successfully',
            211
        );
    }

    public function cancel(Order $order): JsonResponse
    {
        $this->authorize('cancel', $order);
        if ($order->status == 'canceled') {
            return $this->errorResponse('This order has already been canceled', 422);
        } elseif ($order->status == 'completed' || $order->status == 'in_progress' || $order->status == 'assigned_to_worker') {
            return $this->errorResponse('This order cannot be canceled', 422);
        }
        $order->update(['status' => 'canceled']);

        $order->tasks()->delete();
        $order->load(['client.profile', 'package.service.company.region', 'attributes', 'tasks.workgroup.workers.profile']);

        return $this->successResponse(new OrderResource($order), 'Order cancelled and linked field schedules cleared');
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $user = auth()->user();

        $query = Order::with(['client.profile', 'package.service.company', 'tasks.workgroup.leader']);

        if ($user->isAdmin()) {
            // admin sees everything
        } elseif ($user->isCompanyManager()) {
            $company = $user->managedCompanies()->first();
            if (!$company)
                return $this->successResponse([], 'No company registered');

            $query->whereHas('package.service', function ($q) use ($company) {
                $q->where('company_id', $company->id);
            });
        } elseif ($user->role === 'region_manager') {
            $query->whereHas('package.service.company', function ($q) use ($user) {
                $q->where('region_id', $user->managedRegions()->pluck('id'));
            });
        } else {
            $query->where('client_id', $user->id);
        }

        $orders = $query->orderBy('created_at', 'desc')->get();
        return $this->successResponse(OrderResource::collection($orders), 'Orders index fetched successfully');
    }

    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $order->load(['client.profile', 'package.service.company.region', 'attributes', 'tasks.workgroup.workers.profile', 'tasks.workgroup.leader']);

        return $this->successResponse(new OrderResource($order), 'Order detailed parameters retrieved');
    }

   
    private function combinations(array $items, int $k): array
    {
        $results = [];
        $n = count($items);
        if ($k <= 0 || $k > $n)
            return [];

        $indices = range(0, $k - 1);

        while (true) {
            $combo = [];
            foreach ($indices as $i) {
                $combo[] = $items[$i];
            }
            $results[] = $combo;

            // move to next
            $i = $k - 1;
            while ($i >= 0 && $indices[$i] == $i + $n - $k) {
                $i--;
            }
            if ($i < 0)
                break;
            $indices[$i]++;
            for ($j = $i + 1; $j < $k; $j++) {
                $indices[$j] = $indices[$j - 1] + 1;
            }
        }

        return $results;
    }

}
