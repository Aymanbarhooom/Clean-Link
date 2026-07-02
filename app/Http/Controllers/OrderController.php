<?php

namespace App\Http\Controllers\Api;

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
    public function getAvailableSlots(Package $package): JsonResponse
    {
        $service = $package->service;
        $company = $service->company;
        
        // 1. Identify minimum competency skill prerequisites for the service
        $requiredSkillIds = $service->requiredSkills()->pluck('skills.id')->toArray();

        // 2. Fetch company workgroups that collectively possess ALL required skills
        $eligibleWorkgroups = Workgroup::where('company_id', $company->id)
            ->get()
            ->filter(function ($workgroup) use ($requiredSkillIds) {
                $workerSkills = $workgroup->getCombinedSkillIds();
                return empty(array_diff($requiredSkillIds, $workerSkills));
            });

        if ($eligibleWorkgroups->isEmpty()) {
            return $this->successResponse([], 'No qualified workgroups are currently available for this service context');
        }

        // 3. Define company operational boundaries (fallback to 08:00 - 22:00 if not set)
        $startHour = $company->start_hour ? Carbon::createFromTimeString($company->start_hour)->format('H:i') : '08:00';
        $closeHour = $company->close_hour ? Carbon::createFromTimeString($company->close_hour)->format('H:i') : '22:00';

        // 4. Construct a 7-day schedule window starting from tomorrow morning
        $startDate = Carbon::tomorrow();
        $endDate = Carbon::tomorrow()->addDays(6);
        $period = CarbonPeriod::create($startDate, $endDate);

        $scheduleMatrix = [];
        $packageDuration = $package->duration; // in minutes

        foreach ($period as $date) {
            $currentDayKey = $date->format('Y-m-d');
            $scheduleMatrix[$currentDayKey] = [];

            // Slice operational hours into 30-minute arrival window start markers
            $loopTime = Carbon::createFromFormat('Y-m-d H:i', $currentDayKey . ' ' . $startHour);
            $endTimeLimit = Carbon::createFromFormat('Y-m-d H:i', $currentDayKey . ' ' . $closeHour);

            while ($loopTime->copy()->addMinutes($packageDuration)->lte($endTimeLimit)) {
                $slotStart = $loopTime->copy();
                $slotEnd = $loopTime->copy()->addMinutes($packageDuration);
                
                $isSlotAvailable = false;

                // 5. Overlap Scan: Find at least one qualified workgroup that is free during this entire window
                foreach ($eligibleWorkgroups as $workgroup) {
                    $hasOverlapConflict = Order::where('status', '!=', 'canceled')
                        ->whereHas('tasks', function ($query) use ($workgroup) {
                            $query->where('workgroup_id', $workgroup->id);
                        })
                        ->where(function ($query) use ($slotStart, $slotEnd) {
                            // Check standard booking overlap criteria
                            $query->where('start_time', '<', $slotEnd)
                                  ->where('end_time', '>', $slotStart);
                        })
                        ->exists();

                    // If a crew has no scheduling conflicts, this starting time is available!
                    if (!$hasOverlapConflict) {
                        $isSlotAvailable = true;
                        break; // Stop checking other crews for this specific slot
                    }
                }

                if ($isSlotAvailable) {
                    $scheduleMatrix[$currentDayKey][] = $slotStart->format('H:i');
                }

                $loopTime->addMinutes(30); // Advance to the next time interval marker
            }
        }

        return $this->successResponse($scheduleMatrix, 'Available scheduling windows for the next 7 days calculated successfully');
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
