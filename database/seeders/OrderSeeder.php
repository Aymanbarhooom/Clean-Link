<?php

// database/seeders/OrderSeeder.php
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
        // 1. جلب 3 باقات مختلفة لتجربة النظام عليها
        $packages = Package::with('service.company.workTimes')->take(3)->get();
        $clientId = 6; // العميل المستهدف بحسب طلبك

        if ($packages->isEmpty()) {
            $this->command->info("No packages found to seed orders. Please ensure you have at least 3 packages in the database.");
            return;
        }

        // أوقات البدء المطلوبة للطلبات
        $targetHours = ['08:00', '11:00', '14:00'];
        $hourIndex = 0;

        foreach ($packages as $package) {
            $company = $package->service->company;
            $workTimes = $company->workTimes->keyBy('day_of_week');

            // 2. البحث عن أول يوم عمل متاح للشركة ابتداءً من الغد (لضمان ألا يكون يوم عطلة)
            $bookingDate = Carbon::tomorrow();
            $safetyLoop = 0;

            while ($safetyLoop < 7) {
                $dayOfWeek = $bookingDate->dayOfWeek; // 0 = Sunday, ..., 6 = Saturday
                $daySetting = $workTimes->get($dayOfWeek);

                // إذا كان اليوم مسجلاً كدوام وليس عطلة نعتمد هذا التاريخ للحجز
                if ($daySetting && !$daySetting->is_holiday) {
                    break;
                }
                
                $bookingDate->addDay(); // الانتقال لليوم التالي إذا كان عطلة
                $safetyLoop++;
            }

            // 3. إنشاء طلبين (2 Orders) لكل باقة بناءً على الساعات المطلوبة
            for ($orderCount = 1; $orderCount <= 2; $orderCount++) {
                
                // اختيار الساعة الحالية من المصفوفة وتدوير المؤشر
                $chosenHour = $targetHours[$hourIndex % count($targetHours)];
                $hourIndex++;

                // بناء كائنات الوقت الدقيقة للبدء والانتهاء
                $startTime = Carbon::createFromFormat('Y-m-d H:i', $bookingDate->format('Y-m-d') . ' ' . $chosenHour);
                $duration = $package->duration; // مدة الباقة بالدقائق
                $endTime = $startTime->copy()->addMinutes($duration);

                // إنشاء الطلب في قاعدة البيانات مع تفعيل الـ Invoice History
                $order = Order::create([
                    'client_id' => $clientId,
                    'package_id' => $package->id,
                    'location' => 'Damascus, Mezzeh Street, Al-Jalaa Building ' . rand(1, 20),
                    'note' => 'Generated automatically by system mock testing sequence.',
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'duration' => $duration,
                    'status' => 'pending',
                    'total_price' => $package->price, // السعر الأساسي للباقة
                ]);

                // محاكاة إضافة واصفات إضافية (Attributes) للطلب لزيادة السعر الإجمالي
                $availableAttributes = $package->service->attributes;
                if ($availableAttributes->isNotEmpty()) {
                    $randomAttr = $availableAttributes->random();
                    
                    // قفل السعر التاريخي في جدول كسر العلاقة لضمان ثبات الفاتورة
                    $order->attributes()->attach($randomAttr->id, [
                        'qty' => rand(1, 2),
                        'price_at_order' => $randomAttr->pivot->price ?? 10.00
                    ]);

                    // إعادة حساب السعر النهائي المحسوب ديناميكياً للطلب وتحديث السجل
                    $addonsPrice = $order->attributes()->get()->sum(function ($attr) {
                        return $attr->pivot->qty * $attr->pivot->price_at_order;
                    });
                    $order->update(['total_price' => $package->price + $addonsPrice]);
                }
            }
        }
    }
}

