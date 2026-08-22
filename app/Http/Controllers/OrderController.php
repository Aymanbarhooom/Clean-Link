<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderLocationResource;
use App\Models\Company;
use App\Models\Package;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Models\Workgroup;
use App\Services\FirebaseNotificationService;
use App\Services\GoogleRoutesService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function getAvailableSlots(Package $package, Request $request, GoogleRoutesService $routesService): JsonResponse
    {
        $service = $package->service;
        $company = $service->company;
        $packageDuration = $package->duration;

        $validated = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        if (is_null($company->latitude) || is_null($company->longitude)) {
            return $this->errorResponse('Company location is not configured yet', 422);
        }

        $oneWayMinutes = $routesService->calculateDrivingRoute(
            $company->latitude,
            $company->longitude,
            $validated['latitude'],
            $validated['longitude']
        );

        if (is_null($oneWayMinutes)) {
            return $this->errorResponse('Unable to calculate travel time, please try again', 503);
        }

        if ($oneWayMinutes > 60) {
            return $this->errorResponse('Far distance! Please try another company or change your location', 422);
        }

        $travelBufferMinutes = (int) (ceil(($oneWayMinutes * 2) / 30) * 30);

        $requiredSkillIds = $service->requiredSkills()->pluck('skills.id')->toArray();
        $minimumWorkers = (int) ($package->minimum_workers ?? 1);

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
            $oneWayBufferMinutes = (int) (ceil($oneWayMinutes / 30) * 30);

            if ($date->isToday()) {
                $now = Carbon::now();
                $roundedNow = $now->copy();
                if ($now->minute < 30) {
                    $roundedNow->minute(30)->second(0);
                } else {
                    $roundedNow->addHour()->minute(0)->second(0);
                }
                $earliestPossibleStart = $roundedNow->addHour();
                $baseLoopTime = $companyOpenTime->gt($earliestPossibleStart) ? $companyOpenTime : $earliestPossibleStart;
            } else {
                $baseLoopTime = $companyOpenTime;
            }
            $loopTime = $baseLoopTime->copy()->addMinutes($oneWayBufferMinutes);

            $scheduleMatrix[$currentDayKey] = [];

            while ($loopTime->copy()->addMinutes($packageDuration + $travelBufferMinutes)->lte($companyCloseTime)) {
                $slotStart = $loopTime->copy();
                $slotEnd = $loopTime->copy()->addMinutes($packageDuration);
                $slotEffectiveEnd = $slotEnd->copy()->addMinutes($travelBufferMinutes);

                $isSlotAvailable = false;

                $workerCombinations = $this->combinations($eligibleWorkers->values()->all(), $minimumWorkers);

                foreach ($workerCombinations as $combo) {
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
                        if (!$this->isWorkerAvailable($worker, $slotStart, $slotEffectiveEnd)) {
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

    public function checkPrice(Package $package, Request $request): JsonResponse
    {
        if (!$package->is_open_package) {
            return $this->errorResponse('Price check is available only for the Open Package', 422);
        }

        $validated = $request->validate([
            'attributes' => 'nullable|array',
            'attributes.*.id' => 'required|exists:attributes,id',
            'attributes.*.qty' => 'required|integer|min:1',
        ]);

        $service = $package->service;
        $attributeItems = $this->buildServiceAttributeItems($service, $validated['attributes'] ?? []);

        $totals = $this->calculateAttributeTotals($attributeItems, $package->price_after_discount ?? $package->price, (int) $package->duration);

        return $this->successResponse([
            'total_price' => $totals['total_price'],
            'duration' => $totals['duration'],
            'attributes' => $attributeItems,
        ], 'Open Package pricing calculated successfully');
    }

    public function getAvailableSlotsForOpenPackage(Package $package, Request $request, GoogleRoutesService $routesService): JsonResponse
    {
        if (!$package->is_open_package) {
            return $this->errorResponse('Open Package slot calculation is available only for the Open Package', 422);
        }

        $validated = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'attributes' => 'nullable|array',
            'attributes.*.id' => 'required|exists:attributes,id',
            'attributes.*.qty' => 'required|integer|min:1',
        ]);

        $service = $package->service;
        $company = $service->company;

        if (is_null($company->latitude) || is_null($company->longitude)) {
            return $this->errorResponse('Company location is not configured yet', 422);
        }

        $attributeItems = $this->buildServiceAttributeItems($service, $validated['attributes'] ?? []);
        $totals = $this->calculateAttributeTotals($attributeItems, $package->price_after_discount ?? $package->price, (int) $package->duration);

        $oneWayMinutes = $routesService->calculateDrivingRoute(
            $company->latitude,
            $company->longitude,
            $validated['latitude'],
            $validated['longitude']
        );

        if (is_null($oneWayMinutes)) {
            return $this->errorResponse('Unable to calculate travel time, please try again', 503);
        }

        if ($oneWayMinutes > 60) {
            return $this->errorResponse('Far distance! Please try another company or change your location', 422);
        }

        $travelBufferMinutes = (int) (ceil(($oneWayMinutes * 2) / 30) * 30);
        $packageDuration = $totals['duration'];

        $requiredSkillIds = $service->requiredSkills()->pluck('skills.id')->toArray();
        $minimumWorkers = (int) ($totals['duration'] / 30 ?? 2);

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
            $oneWayBufferMinutes = (int) (ceil($oneWayMinutes / 30) * 30);

            if ($date->isToday()) {
                $now = Carbon::now();
                $roundedNow = $now->copy();
                if ($now->minute < 30) {
                    $roundedNow->minute(30)->second(0);
                } else {
                    $roundedNow->addHour()->minute(0)->second(0);
                }
                $earliestPossibleStart = $roundedNow->addHour();
                $baseLoopTime = $companyOpenTime->gt($earliestPossibleStart) ? $companyOpenTime : $earliestPossibleStart;
            } else {
                $baseLoopTime = $companyOpenTime;
            }
            $loopTime = $baseLoopTime->copy()->addMinutes($oneWayBufferMinutes);

            $scheduleMatrix[$currentDayKey] = [];

            while ($loopTime->copy()->addMinutes($packageDuration + $travelBufferMinutes)->lte($companyCloseTime)) {
                $slotStart = $loopTime->copy();
                $slotEnd = $loopTime->copy()->addMinutes($packageDuration);
                $slotEffectiveEnd = $slotEnd->copy()->addMinutes($travelBufferMinutes);

                $isSlotAvailable = false;

                $workerCombinations = $this->combinations($eligibleWorkers->values()->all(), $minimumWorkers);

                foreach ($workerCombinations as $combo) {
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
                        if (!$this->isWorkerAvailable($worker, $slotStart, $slotEffectiveEnd)) {
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

        return $this->successResponse([
            'slots' => $scheduleMatrix,
            'duration' => $packageDuration,
            'travel_buffer_minutes' => $travelBufferMinutes,
            'one_way_minutes' => $oneWayMinutes,
            'total_price' => $totals['total_price'],
        ], 'Open Package slots calculated successfully');
    }

    public function bookOpenPackage(Request $request, GoogleRoutesService $routesService): JsonResponse
    {
        if (auth()->user()->role !== 'client') {
            return $this->errorResponse('Access restricted to registered customer accounts', 403);
        }

        $validated = $request->validate([
            'package_id' => 'required|exists:packages,id',
            'location' => 'required|string|max:500',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'start_time' => 'required|date|after:now',
            'note' => 'nullable|string|max:1000',
            'attributes' => 'nullable|array',
            'attributes.*.id' => 'required|exists:attributes,id',
            'attributes.*.qty' => 'required|integer|min:1',

            'payment_method' => 'required|in:electric,manual',
        ]);

        $package = Package::with('service.company')->find($validated['package_id']);
        if (!$package->is_open_package) {
            return $this->errorResponse('This booking endpoint is available only for the Open Package', 422);
        }

        $service = $package->service;
        $company = $service->company;

        if (is_null($company->latitude) || is_null($company->longitude)) {
            return $this->errorResponse('Company location is not configured yet', 422);
        }

        $oneWayMinutes = $routesService->calculateDrivingRoute(
            $company->latitude,
            $company->longitude,
            $validated['latitude'],
            $validated['longitude']
        );

        if (is_null($oneWayMinutes)) {
            return $this->errorResponse('Unable to calculate travel time, please try again', 503);
        }

        $travelBufferMinutes = (int) (ceil(($oneWayMinutes * 2) / 30) * 30);
        $attributeItems = $this->buildServiceAttributeItems($service, $validated['attributes'] ?? []);
        $totals = $this->calculateAttributeTotals($attributeItems, $package->price_after_discount ?? $package->price, (int) $package->duration);

        $startTime = Carbon::createFromFormat('Y-m-d H:i:s', $request->start_time, 'Asia/Riyadh');
        $totalDuration = $totals['duration'];
        $endTime = $startTime->copy()->addMinutes($totalDuration);
        $effectiveEndTime = $endTime->copy()->addMinutes($travelBufferMinutes);

        $pivotPayload = [];
        foreach ($attributeItems as $item) {
            $pivotPayload[$item['id']] = [
                'qty' => $item['qty'],
                'price_at_order' => $item['price'],
            ];
        }

        $order = DB::transaction(function () use ($validated, $package, $startTime, $effectiveEndTime, $totalDuration, $travelBufferMinutes, $totals, $pivotPayload) {
            $paymentStatus = $validated['payment_method'] === 'electric' ? 'pending' : 'held';
            $order = Order::create([
                'client_id' => auth()->id(),
                'package_id' => $package->id,
                'location' => $validated['location'],
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'note' => $validated['note'] ?? null,
                'start_time' => $startTime,
                'end_time' => $effectiveEndTime,
                'duration' => $totalDuration,
                'travel_buffer_minutes' => $travelBufferMinutes,
                'status' => 'pending',
                'total_price' => $totals['total_price'],

                'payment_method' => $validated['payment_method'],
                'payment_status' => $validated['payment_method'] === 'electric'
                    ? 'pending'
                    : 'held',
                'is_done_with_admin' => false,
                'is_company_paid' => $validated['payment_method'] === 'manual',
            ]);

            $order->payments()->create([
                'user_id' => auth()->id(),
                'amount' => $order->total_price,
                'currency' => 'usd',
                'payment_method' => $validated['payment_method'],
                'payment_status' => $paymentStatus,
                'paid_at' => $validated['payment_method'] === 'cash' ? now() : null,
            ]);

            $order->calculateAndSetPaymentShares();

            if (!empty($pivotPayload)) {
                $order->attributes()->attach($pivotPayload);
            }

            return $order;
        });

        $order->load(['package.service', 'attributes']);

        try {
            $service = $package->service;
            $company = $service->company;
            $requiredSkillIds = $service->requiredSkills()->pluck('skills.id')->toArray();
            $minimumWorkers = (int) ($totals['duration'] / 30 ?? 2);

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
                        if (!$this->isWorkerAvailable($worker, $startTime, $effectiveEndTime)) {
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
                            'body_en' => "You have been assigned a new task for Order #{$order->id}. يرجى التحقق من لوحة التحكم الخاصة بك لمزيد من التفاصيل.",
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
        }

        return $this->successResponse(
            new OrderResource($order),
            'Open Package booking submitted and placed under review successfully',
            211
        );
    }

    public function store(Request $request, GoogleRoutesService $routesService): JsonResponse
    {
        if (auth()->user()->role !== 'client') {
            return $this->errorResponse('Access restricted to registered customer accounts', 403);
        }

        $validated = $request->validate([
            'package_id' => 'required|exists:packages,id',
            'location' => 'required|string|max:500',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'start_time' => 'required|date|after:now',
            'note' => 'nullable|string|max:1000',

            'payment_method' => 'required|in:electric,manual',
        ]);

        $package = Package::with('service.company')->find($validated['package_id']);
        $service = $package->service;
        $company = $service->company;

        if (is_null($company->latitude) || is_null($company->longitude)) {
            return $this->errorResponse('Company location is not configured yet', 422);
        }

        $oneWayMinutes = $routesService->calculateDrivingRoute(
            $company->latitude,
            $company->longitude,
            $validated['latitude'],
            $validated['longitude']
        );

        if (is_null($oneWayMinutes)) {
            return $this->errorResponse('Unable to calculate travel time, please try again', 503);
        }

        $travelBufferMinutes = (int) (ceil(($oneWayMinutes * 2) / 30) * 30);

        $startTime = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $request->start_time,
            'Asia/Riyadh'
        );

        $baseDuration = (int) $package->duration;
        $basePrice = $package->price_after_discount ?? $package->price;

        $attributeCalculationItems = [];
        $pivotPayload = [];

        $attributeTotals = $this->calculateAttributeTotals(
            $attributeCalculationItems,
            $basePrice,
            $baseDuration
        );

        $totalDuration = $attributeTotals['duration'];

        $endTime = $startTime->copy()->addMinutes($totalDuration);

        $effectiveEndTime = $endTime->copy()->addMinutes($travelBufferMinutes);

        $totalDuration += $travelBufferMinutes;


        $order = DB::transaction(function () use (
            $effectiveEndTime,
            $travelBufferMinutes,
            $validated,
            $package,
            $service,
            $startTime,
            $endTime,
            $totalDuration,
            $attributeTotals,
            $pivotPayload
        ) {
            $paymentStatus = $validated['payment_method'] === 'electric' ? 'pending' : 'held';
            $order = Order::create([
                'client_id' => auth()->id(),
                'package_id' => $package->id,

                'location' => $validated['location'],
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'note' => $validated['note'] ?? null,

                'start_time' => $startTime,
                'end_time' => $effectiveEndTime,
                'duration' => $totalDuration,
                'travel_buffer_minutes' => $travelBufferMinutes,

                'status' => 'pending',

                'total_price' => $attributeTotals['total_price'],

                'payment_method' => $validated['payment_method'],

                'payment_status' => $validated['payment_method'] === 'electric'
                    ? 'pending'
                    : 'held',

                'is_done_with_admin' => false,

                'is_company_paid' => $validated['payment_method'] === 'manual',
            ]);

            $order->payments()->create([
                'user_id' => auth()->id(),
                'amount' => $order->total_price,
                'currency' => 'usd',
                'payment_method' => $validated['payment_method'],
                'payment_status' => $paymentStatus,
                'paid_at' => $validated['payment_method'] === 'cash' ? now() : null,
            ]);



            $order->calculateAndSetPaymentShares();

            if (!empty($pivotPayload)) {
                $order->attributes()->attach($pivotPayload);
            }

            return $order;
        });

        $order->load(['package.service', 'attributes']);

        try {
            $service = $package->service;
            $company = $service->company;

            $requiredSkillIds = $service->requiredSkills()
                ->pluck('skills.id')
                ->toArray();

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

                $combinations = $this->combinations(
                    $eligibleWorkers->values()->all(),
                    $minimumWorkers
                );

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

                        if (!$this->isWorkerAvailable(
                            $worker,
                            $startTime,
                            $effectiveEndTime
                        )) {
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

                    $leader = collect($found)
                        ->sortByDesc(fn($u) => $u->workerProfile->rating ?? 0)
                        ->first();

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

                        $order->update([
                            'status' => 'assigned_to_worker'
                        ]);
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

                            $notificationTitle =
                                $newTaskNotifications[$token->lang]['title']
                                ?? $newTaskNotifications['en']['title'];

                            $notificationBody =
                                $newTaskNotifications[$token->lang]['body']
                                ?? $newTaskNotifications['en']['body'];

                            app(FirebaseNotificationService::class)
                                ->sendPushNotification(
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
        }

        return $this->successResponse(
            new OrderResource($order),
            'Booking submitted and placed under review successfully',
            211
        );
    }


    public function cancel(Order $order): JsonResponse
    {
        $user = auth()->user();
        $this->authorize('cancel', $order);

        if ($order->status == 'canceled') {
            return $this->errorResponse('This order has already been canceled', 422);
        } elseif ($order->status == 'completed' || $order->status == 'in_progress') {
            return $this->errorResponse('This order cannot be canceled', 422);
        }

        if ($order->payment_method === 'electric' && $order->stripe_payment_intent_id) {
            try {
                $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

                if ($order->payment_status === 'held') {
                    $stripe->paymentIntents->cancel($order->stripe_payment_intent_id);
                    $order->update(['payment_status' => 'refunded']);
                } elseif (in_array($order->payment_status, ['paid', 'captured'], true)) {
                    $stripe->refunds->create([
                        'payment_intent' => $order->stripe_payment_intent_id,
                    ]);
                    $order->update(['payment_status' => 'refunded']);
                    $order->payments()->latest()->update([
                        'payment_status' => 'refunded'
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error('Stripe Refund/Cancel Failed for Order #' . $order->id . ': ' . $e->getMessage());
            }
        }

        $order->update(['status' => 'canceled']);
        $order->tasks()->delete();

        $order->load(['client.profile', 'package.service.company.region', 'attributes', 'tasks.workgroup.workers.profile']);

        return $this->successResponse(new OrderResource($order), 'Order cancelled and linked field schedules cleared');
    }

public function index(Request $request): JsonResponse
{
    $this->authorize('viewAny', Order::class);

    $validated = $request->validate([
        'status' => ['nullable', 'string', 'in:pending,assigned_to_worker,in_process,in_progress,completed,canceled'],
        'payment_status' => ['nullable', 'string', 'in:pending,held,captured,paid,refunded'],
        'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        'page' => 'sometimes|integer|min:1',
        'company_id' => ['sometimes', 'integer', 'exists:companies,id'], // تعديل هنا لجعل company_id غير مطلوب
    ]);

    $perPage = $validated['per_page'] ?? 10;

    $user = auth()->user();

    // إذا كان المستخدم غير إداري، تحقق من الأذونات
    if (!$user->isAdmin() && isset($validated['company_id'])) {
        $company = Company::find($validated['company_id']);
        if (!$user->canManageCompany($company)) {
            return $this->errorResponse('You do not have permission to view orders for this company', 403);
        }
    }

    $query = Order::with(['client.profile', 'package.service.company', 'tasks.workgroup.leader.profile']);

    // إضافة شرط لتصفية الطلبات حسب company_id إذا كان موجودًا
    if (isset($validated['company_id'])) {
        $query->whereHas('package.service', function (Builder $serviceQuery) use ($validated) {
            $serviceQuery->where('company_id', $validated['company_id']);
        });
    }

    // إضافة شرط لتصفية الطلبات حسب الحالة
    if (!empty($validated['status'])) {
        $query->where('status', $validated['status']);
    }

    // إضافة شرط لتصفية الطلبات حسب حالة الدفع
    if (!empty($validated['payment_status'])) {
        $normalizedPaymentStatus = $validated['payment_status'] === 'paid' ? 'captured' : $validated['payment_status'];
        $query->where('payment_status', $normalizedPaymentStatus);
    }

    // استرجاع الطلبات مع الترتيب والتقسيم
    $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

    // إعداد البيانات للاستجابة
    $responseData = [
        'data' => OrderResource::collection($orders->items()),
        'pagination' => [
            'current_page' => $orders->currentPage(),
            'per_page' => $orders->perPage(),
            'total' => $orders->total(),
            'last_page' => $orders->lastPage(),
            'from' => $orders->firstItem(),
            'to' => $orders->lastItem(),
            'has_more_pages' => $orders->hasMorePages(),
        ]
    ];

    return $this->successResponse($responseData, 'Orders index fetched successfully');
}

    /**
     * Return all visible orders that have usable coordinates for map markers.
     */
public function locations(Request $request): JsonResponse
{
    $this->authorize('viewAny', Order::class);

    $validated = $request->validate([
        'company_id' => ['sometimes', 'integer', 'exists:companies,id'], // company_id غير مطلوب
    ]);

    $user = auth()->user();
    $company = isset($validated['company_id']) ? Company::find($validated['company_id']) : null;

    // تحقق من الأذونات فقط إذا كانت company_id موجودة
    if (!$user->isAdmin() && $company && !$user->canManageCompany($company)) {
        return $this->errorResponse('You do not have permission to view orders for this company', 403);
    }

    $query = Order::query()
        ->select([
            'id',
            'client_id',
            'package_id',
            'latitude',
            'longitude',
            'status',
            'location',
            'start_time',
            'end_time',
            'total_price',
        ])
        ->with([
            'client:id,fullname',
            'package:id,service_id,name_ar,name_en',
            'package.service:id,name_ar,name_en',
        ])
        ->whereNotNull('latitude')
        ->whereNotNull('longitude');

    // إضافة شرط لتصفية الطلبات حسب company_id إذا كان موجودًا
    if ($company) {
        $query->whereHas('package.service', function (Builder $serviceQuery) use ($company) {
            $serviceQuery->where('company_id', $company->id);
        });
    }

    $orders = $query->orderByDesc('created_at')->get();

    return $this->successResponse(
        OrderLocationResource::collection($orders),
        'Order locations fetched successfully'
    );
}


    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $order->load(['client.profile', 'package.service.company.region', 'attributes', 'tasks.workgroup.workers.profile', 'tasks.workgroup.leader.profile']);

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

    private function buildServiceAttributeItems(Service $service, array $submittedAttributes): array
    {
        if (empty($submittedAttributes)) {
            return [];
        }

        $loadedAttributes = $service->attributes()->get()->keyBy('id');

        $items = [];
        foreach ($submittedAttributes as $item) {
            $serviceAttribute = $loadedAttributes->get($item['id']);
            if (!$serviceAttribute) {
                continue;
            }

            $items[] = [
                'id' => $serviceAttribute->id,
                'qty' => (int) $item['qty'],
                'price' => (float) $serviceAttribute->pivot->price,
                'duration' => (int) $serviceAttribute->pivot->duration,
            ];
        }

        return $items;
    }

    private function calculateAttributeTotals(array $attributeInputs, float $basePrice, int $baseDuration): array
    {
        $runningTotalPrice = $basePrice;
        $runningTotalDuration = $baseDuration;

        foreach ($attributeInputs as $item) {
            $qty = (int) ($item['qty'] ?? 1);
            $price = (float) ($item['price'] ?? 0.0);
            $duration = (int) ($item['duration'] ?? 0);

            $runningTotalPrice += $price * $qty;
            $runningTotalDuration += $duration * $qty;
        }

        return [
            'total_price' => $runningTotalPrice,
            'duration' => $this->roundToNextHalfHour($runningTotalDuration),
        ];
    }

    private function roundToNextHalfHour(int $minutes): int
    {
        if ($minutes <= 0) {
            return 0;
        }

        $step = 30;

        return (int) ceil($minutes / $step) * $step;
    }

    private function isWorkerAvailable(User $worker, Carbon $newStart, Carbon $newEffectiveEnd): bool
    {
        $conflict = Order::where('status', '!=', 'canceled')
            ->whereHas('tasks.workgroup.workers', function ($q) use ($worker) {
                $q->where('users.id', $worker->id);
            })
            ->where('start_time', '<', $newEffectiveEnd)
            ->whereRaw('DATE_ADD(end_time, INTERVAL travel_buffer_minutes MINUTE) > ?', [$newStart])
            ->exists();

        return !$conflict;
    }
}
