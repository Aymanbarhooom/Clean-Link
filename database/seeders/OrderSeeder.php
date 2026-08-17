<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\Order;
use App\Models\WorkTime;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $packages = Package::with('service.company.workTimes')->take(3)->get();
        $clientId = 6; 

        if ($packages->isEmpty()) {
            $this->command->info("No packages found to seed orders. Please ensure you have at least 3 packages in the database.");
            return;
        }

        $targetHours = ['08:00', '11:00', '14:00'];
        $hourIndex = 0;

        foreach ($packages as $package) {
            $company = $package->service->company;
            $workTimes = $company->workTimes->keyBy('day_of_week');

            $bookingDate = Carbon::tomorrow();
            $safetyLoop = 0;

            while ($safetyLoop < 7) {
                $dayOfWeek = $bookingDate->dayOfWeek; 
                $daySetting = $workTimes->get($dayOfWeek);

                if ($daySetting && !$daySetting->is_holiday) {
                    break;
                }
                
                $bookingDate->addDay();
                $safetyLoop++;
            }

            for ($orderCount = 1; $orderCount <= 2; $orderCount++) {
                
                $chosenHour = $targetHours[$hourIndex % count($targetHours)];
                $hourIndex++;

                $startTime = Carbon::createFromFormat('Y-m-d H:i', $bookingDate->format('Y-m-d') . ' ' . $chosenHour);
                $duration = $package->duration; 
                $endTime = $startTime->copy()->addMinutes($duration);

                $order = Order::create([
                    'client_id' => $clientId,
                    'package_id' => $package->id,
                    'location' => 'Damascus, Mezzeh Street, Al-Jalaa Building ' . rand(1, 20),
                    'note' => 'Generated automatically by system mock testing sequence.',
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'duration' => $duration,
                    'status' => 'pending',
                    'total_price' => $package->price, 
                ]);

                $availableAttributes = $package->service->attributes;
                if ($availableAttributes->isNotEmpty()) {
                    $randomAttr = $availableAttributes->random();
                    
                    $order->attributes()->attach($randomAttr->id, [
                        'qty' => rand(1, 2),
                        'price_at_order' => $randomAttr->pivot->price ?? 10.00
                    ]);

                    $addonsPrice = $order->attributes()->get()->sum(function ($attr) {
                        return $attr->pivot->qty * $attr->pivot->price_at_order;
                    });
                    $order->update(['total_price' => $package->price + $addonsPrice]);
                }
            }
        }
    }
}

