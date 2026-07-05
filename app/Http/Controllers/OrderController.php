<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
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

        $order->load(['package.service', 'attributes']);

        return $this->successResponse(
            new OrderResource($order),
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

    /**
     * استعراض الطلبات بناءً على الصلاحيات والأدوار المحددة في الـ Policy
     * Route: GET /api/orders
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $user = auth()->user();

        // بناء الاستعلام مع الـ Eager Loading لمنع الـ N+1 Query Problem
        $query = Order::with(['client.profile', 'package.service.company', 'tasks.workgroup']);

        // تطبيق الفلترة الصارمة بناءً على دور المستخدم
        if ($user->isAdmin()) {
            // الأدمن يرى كل شيء في النظام
        } elseif ($user->isCompanyManager()) {
            // مدير الشركة يرى طلبات باقات خدمات شركته فقط
            $company = $user->managedCompanies()->first();
            if (!$company)
                return $this->successResponse([], 'No company registered');

            $query->whereHas('package.service', function ($q) use ($company) {
                $q->where('company_id', $company->id);
            });
        } elseif ($user->role === 'region_manager') {
            // مدير المنطقة يرى طلبات الشركات الواقعة في منطقته الإدارية
            $query->whereHas('package.service.company', function ($q) use ($user) {
                $q->where('region_id', $user->managedRegions()->pluck('id'));
            });
        } else {
            // العميل يرى طلباته الشخصية فقط
            $query->where('client_id', $user->id);
            $query->orderBy('created_at', 'desc');
            return $this->successResponse(OrderResource::collection($query->get()), 'Orders index fetched successfully');
        }
        
        return $this->successResponse($query->orderBy('created_at', 'desc')->get(), 'Orders index fetched successfully');
    }

    /**
     * عرض تفاصيل طلب محدد بعد فحص الصلاحية
     * Route: GET /api/orders/{order}
     */
    public function show(Order $order): JsonResponse
    {
        // فحص الصلاحية عبر الـ Policy (العميل يرى طلبه، المدير يرى طلبات شركته...)
        $this->authorize('view', $order);

        $order->load(['client.profile', 'package.service.company.region', 'attributes', 'tasks.workgroup.workers.profile']);

        return $this->successResponse(new OrderResource($order), 'Order detailed parameters retrieved');
    }

        /**
     * إسناد الطلب لورشة عمل معينة وإنشاء المهمة (خاص بمدير الشركة)
     * Route: POST /api/orders/{order}/assign
     */
    public function assignToWorkgroup(Request $request, Order $order): JsonResponse
    {
        // التحقق من أن المستخدم الحالي هو مدير الشركة التي تملك هذه الخدمة
        if (auth()->user()->id !== $order->package->service->company->manager_id) {
            return $this->errorResponse('Unauthorized company domain access block', 403);
        }

        $validated = $request->validate([
            'workgroup_id' => 'required|exists:workgroups,id',
        ]);

        $workgroup = Workgroup::find($validated['workgroup_id']);

        // التأكد من أن الورشة المحددة تابعة لنفس الشركة
        if ($workgroup->company_id !== $order->package->service->company_id) {
            return $this->errorResponse('The selected workgroup does not belong to your company', 422);
        }

        // تنفيذ عملية الإسناد المزدوجة داخل Transaction لضمان سلامة البيانات
        DB::transaction(function () use ($order, $workgroup) {
            // 1. إنشاء المهمة المرتبطة بالورشة
            $order->tasks()->create([
                'workgroup_id' => $workgroup->id,
                'status' => 'pending' // تبدأ الحالة تلقائياً بـ "في الطريق" عند الإسناد
            ]);

            // 2. تحديث حالة الطلب الأساسي للعميل ليعرف أن هناك فريقاً تم تعيينه
            $order->update(['status' => 'assigned_to_worker']);
        });

        return $this->successResponse($order->load('tasks.workgroup'), 'Order successfully assigned to the workgroup crew', 211);
    }

        /**
     * Fetch qualified and available workgroups capable of handling a specific order.
     * Assists the Company Manager by filtering crews by required skills and scheduling availability.
     * Route: GET /api/orders/{order}/qualified-groups
     */
    public function getQualifiedGroups(Order $order): JsonResponse
    {
        $user = auth()->user();
        $service = $order->package->service;
        $company = $service->company;

        // Security Boundary: Ensure only the managing Company Manager can query this data
        if ($user->id !== $company->manager_id && !$user->isAdmin()) {
            return $this->errorResponse('Unauthorized company domain access block', 403);
        }

        // 1. Identify the baseline prerequisite skill IDs required by this service
        $requiredSkillIds = $service->requiredSkills()->pluck('skills.id')->toArray();

        $orderStart = $order->start_time;
        $orderEnd = $order->end_time;

        // 2. Fetch all company crews, then apply real-time double filters
        $qualifiedGroups = Workgroup::where('company_id', $company->id)
            ->with(['leader.profile', 'workers.profile', 'workers.workerProfile.skills'])
            ->get()
            ->filter(function ($workgroup) use ($requiredSkillIds, $orderStart, $orderEnd) {
                
                // --- CRITERIA 1: Competency Skill Matching ---
                $groupSkills = $workgroup->getCombinedSkillIds();
                $hasSkillsMatch = empty(array_diff($requiredSkillIds, $groupSkills));
                
                if (!$hasSkillsMatch) {
                    return false; // Discard if the team lacks the required training credentials
                }

                // --- CRITERIA 2: Calendar Availability / Scheduling Overlaps ---
                $hasTimeConflict = Order::where('status', '!=', 'canceled')
                    ->whereHas('tasks', function ($query) use ($workgroup) {
                        $query->where('workgroup_id', $workgroup->id);
                    })
                    ->where(function ($query) use ($orderStart, $orderEnd) {
                        // Standard scheduling block collision intersection check
                        $query->where('start_time', '<', $orderEnd)
                              ->where('end_time', '>', $orderStart);
                    })
                    ->exists();

                return !$hasTimeConflict; // Keep the group only if they have no scheduling conflicts
            })
            ->values(); // Reset the collection index keys for clean JSON array sequence formatting

        return $this->successResponse(
            $qualifiedGroups, 
            'Qualified and available workgroups filtered successfully for dispatch'
        );
    }

}
