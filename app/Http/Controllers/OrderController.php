<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Order;
use App\Models\Workgroup;
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

    /**
     * 7-Day Advanced Intersection Scheduling Engine.
     * Route: GET /api/packages/{package}/available-slots
     */
    /**
     * محرك حساب الأوقات الشاغرة المطور (دعم جدول الدوام، العطل، والحجز الفوري من اليوم)
     * Route: GET /api/packages/{package}/available-slots
     */
    public function getAvailableSlots(Package $package): JsonResponse
    {
        $service = $package->service;
        $company = $service->company;
        $packageDuration = $package->duration; // بالدقائق

        $requiredSkillIds = $service->requiredSkills()->pluck('skills.id')->toArray();
        $eligibleWorkgroups = Workgroup::where('company_id', $company->id)
            ->get()
            ->filter(function ($workgroup) use ($requiredSkillIds) {
                $workerSkills = $workgroup->getCombinedSkillIds();
                return empty(array_diff($requiredSkillIds, $workerSkills));
            });

        if ($eligibleWorkgroups->isEmpty()) {
            return $this->successResponse([], 'No qualified workgroups are currently available for this service');
        }

        // 2. جلب جدول مواعيد العمل للشركة بالكامل وتخزينه كـ Collection للبحث السريع بكود اليوم
        $workTimes = $company->workTimes()->get()->keyBy('day_of_week');

        // 3. بناء مصفوفة الـ 7 أيام تبدأ من "اليوم" (Now) وتنتهي بعد 6 أيام
        $startDate = Carbon::now();
        $endDate = Carbon::now()->addDays(6);
        $period = CarbonPeriod::create($startDate, $endDate);

        $scheduleMatrix = [];

        foreach ($period as $date) {
            $currentDayKey = $date->format('Y-m-d');
            $dayOfWeek = $date->dayOfWeek; // يرجع 0 للأحد، 1 للاثنين... حتى 6 للسبت متوافق مع جدولك

            // فحص هل هذا اليوم مسجل في جدول الدوام وهل هو عطلة؟
            $daySetting = $workTimes->get($dayOfWeek);
            if (!$daySetting || $daySetting->is_holiday || !$daySetting->open_at || !$daySetting->close_at) {
                // إذا كان اليوم عطلة، نرجعه كمصفوفة فارغة لـ لفرونت إند ولا نحسب له أي Slots
                $scheduleMatrix[$currentDayKey] = [];
                continue;
            }

            // تحديد ساعات العمل الفتح والإغلاق لهذا اليوم بالتحديد من الـ Database
            $openHourStr = Carbon::parse($daySetting->open_at)->format('H:i');
            $closeHourStr = Carbon::parse($daySetting->close_at)->format('H:i');

            // تحويل الساعات الممتدة لنصوص إلى Object زمني كامل مربوط بتاريخ اليوم الحالي في الحلقة
            $companyOpenTime = Carbon::createFromFormat('Y-m-d H:i', $currentDayKey . ' ' . $openHourStr);
            $companyCloseTime = Carbon::createFromFormat('Y-m-d H:i', $currentDayKey . ' ' . $closeHourStr);

            // 4. تطبيق معادلة تقريب الموعد الحالي (إذا كنا نفحص اليوم الحاضر)
            if ($date->isToday()) {
                $now = Carbon::now();

                // تقريب الدقائق إلى الخلف (Round Down) إلى 00 أو 30
                $roundedNow = $now->copy();

                // إضافة 30 دقيقة إذا كانت الدقائق أقل من 30
                if ($now->minute < 30) {
                    $roundedNow->minute(30)->second(0);
                } else {
                    // إذا كانت الدقائق 30 أو أكثر، نضيف ساعة ونضبط الدقائق إلى 0
                    $roundedNow->addHour()->minute(0)->second(0);
                }


                // إضافة ساعة كاملة كـ (Buffer / هامش أمان) لمعالجة الـ Slots والتحضير
                $earliestPossibleStart = $roundedNow->addHour();

                // نقطة البدء لليوم تكون الأكبر بين: (وقت فتح الشركة) أو (الوقت الحالي المقرب + ساعة الأمان)
                $loopTime = $companyOpenTime->gt($earliestPossibleStart) ? $companyOpenTime : $earliestPossibleStart;
            } else {
                // للأيام المستقبلية: البدء يكون دائماً من ساعة فتح الشركة الرسمية في ذلك اليوم
                $loopTime = $companyOpenTime;
            }

            $scheduleMatrix[$currentDayKey] = [];

            // 5. حلقة فحص الـ Slots المتاحة كل 30 دقيقة
            while ($loopTime->copy()->addMinutes($packageDuration)->lte($companyCloseTime)) {
                $slotStart = $loopTime->copy();
                $slotEnd = $loopTime->copy()->addMinutes($packageDuration);

                $isSlotAvailable = false;

                // فحص التقاطع الجدولي (Overlap) مع الورش المؤهلة
                foreach ($eligibleWorkgroups as $workgroup) {
                    $hasOverlapConflict = Order::where('status', '!=', 'canceled')
                        ->whereHas('tasks', function ($query) use ($workgroup) {
                            $query->where('workgroup_id', $workgroup->id);
                        })
                        ->where(function ($query) use ($slotStart, $slotEnd) {
                            $query->where('start_time', '<', $slotEnd)
                                ->where('end_time', '>', $slotStart);
                        })
                        ->exists();

                    if (!$hasOverlapConflict) {
                        $isSlotAvailable = true;
                        break;
                    }
                }

                if ($isSlotAvailable) {
                    $scheduleMatrix[$currentDayKey][] = $slotStart->format('H:i');
                }

                $loopTime->addMinutes(30); // الانتقال لنصف الساعة التالية
            }
        }

        return $this->successResponse($scheduleMatrix, 'Dynamic slots mapped across active work-time sheets successfully');
    }


    /**
     * Store and secure a client order with real-time financial tracking.
     * Route: POST /api/orders
     */
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

            // Nested pricing addons validation parameters
            'attributes' => 'nullable|array',
            'attributes.*.id' => 'required|exists:attributes,id',
            'attributes.*.qty' => 'required|integer|min:1',
        ]);

        $package = Package::with('service.company')->find($validated['package_id']);
        $service = $package->service;

        // Calculate time parameters based on the core package selection
        $startTime = Carbon::parse($validated['start_time']);
        $totalDuration = $package->duration;
        $endTime = $startTime->copy()->addMinutes($totalDuration);

        // Process order mapping inside a safe database transaction block
        $order = DB::transaction(function () use ($validated, $package, $service, $startTime, $endTime, $totalDuration) {

            // 1. Create the base client order profile
            $order = Order::create([
                'client_id' => auth()->id(),
                'package_id' => $package->id,
                'location' => $validated['location'],
                'note' => $validated['note'] ?? null,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration' => $totalDuration,
                'status' => 'pending',
                'total_price' => $package->price, // Set initial base price
            ]);

            $runningTotalPrice = $package->price;

            // 2. Lock in custom attribute pricing variants
            if (!empty($validated['attributes'])) {
                $pivotPayload = [];

                foreach ($validated['attributes'] as $item) {
                    // Extract historic values from the service definition pivot
                    $serviceAttributePivot = $service->attributes()->where('attributes.id', $item['id'])->first();

                    // Fallback to 0.00 if the company hasn't configured custom pricing for this attribute
                    $priceAtOrder = $serviceAttributePivot ? $serviceAttributePivot->pivot->price : 0.00;

                    $pivotPayload[$item['id']] = [
                        'qty' => $item['qty'],
                        'price_at_order' => $priceAtOrder,
                    ];

                    $runningTotalPrice += ($priceAtOrder * $item['qty']);
                }

                // Attach to the order invoice history ledger
                $order->attributes()->attach($pivotPayload);
            }

            // 3. Update the final calculated total cost
            $order->update(['total_price' => $runningTotalPrice]);

            return $order;
        });

        return $this->successResponse(
            $order->load(['package.service', 'attributes']),
            'Booking submitted and placed under review successfully',
            211
        );
    }

    /**
     * Cancel a pending order entry safely.
     * Route: POST /api/orders/{order}/cancel
     */
    public function cancel(Order $order): JsonResponse
    {
        // Authorize using the Order Policy rules we defined earlier
        $this->authorize('cancel', $order);

        $order->update(['status' => 'canceled']);

        // Cascade delete or cancel any assigned tasks linked to this order
        $order->tasks()->delete();

        return $this->successResponse($order, 'Order cancelled and linked field schedules cleared');
    }
}
