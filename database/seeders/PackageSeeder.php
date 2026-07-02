<?php

// database/seeders/PackageSeeder.php
namespace Database\Seeders;

use App\Models\Service;
use App\Models\Package;
use App\Models\Category;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $homeCleaning = Category::where('name_en', 'Home Cleaning')->orWhere('name_en', 'Home')->first();
        $carWash = Category::where('name_en', 'Car Detailing & Wash')->orWhere('name_en', 'Car')->first();

        // Retrieve all 10 pre-seeded corporate services
        $services = Service::all();

        foreach ($services as $service) {
            
            // Check if the parent company or service links to the Residential Cleaning Vertical
            if ($service->category_id === $homeCleaning?->id || $service->company?->category_id === $homeCleaning?->id) {
                
                // Package 1: Studio Layout
                Package::create([
                    'service_id' => $service->id,
                    'name_ar' => 'باقة الاستوديو',
                    'name_en' => 'Studio Package',
                    'duration' => 30,
                    'price' => 80,
                    'price_after_discount' => $service->discount > 0 ? 80 - (80 * ($service->discount/100)) : 80,
                    'details_ar' => [
                        'مثالية للمساحات التي تقل عن 60 متر مربع',
                        'تشمل غرفتين وحمام واحد فقط',
                    ],
                    'details_en' => [
                        'Ideal for spaces under 60m²',
                        'Includes 2 rooms and 1 bathroom',
                    ],
                ]);

                // Package 2: Standard Flat Layout
                Package::create([
                    'service_id' => $service->id,
                    'name_ar' => 'باقة الشقة القياسية',
                    'name_en' => 'Flat Package',
                    'duration' => 60,
                    'price' => 60,
                    'price_after_discount' => $service->discount > 0 ? 60 - (60 * ($service->discount/100)) : 60,
                    'details_ar' => [
                        'تغطي الشقق العائلية العادية حتى 140 متر مربع',
                        'تنظيف عميق لـ 3 غرف وحمامين مخصصين',
                        'مسح وإزالة الدهون من الخزائن الخارجية للمطبخ'
                    ],
                    'details_en' => [
                        'Covers typical family flats up to 140m²',
                        'Deep wash of 3 rooms & 2 dedicated bathrooms',
                        'Kitchen outer cabinets grease wipe'
                    ],
                ]);

                // Package 3: Premium Multi-Story Building
                Package::create([
                    'service_id' => $service->id,
                    'name_ar' => 'باقة الدوبلكس والمباني الكاملة',
                    'name_en' => 'Duplex & Building Package',
                    'duration' => 90,
                    'price' => 108,
                    'price_after_discount' => $service->discount > 0 ? 108 - (108 * ($service->discount/100)) : 108,
                    'details_ar' => [
                        'مناسبة للشقق الدوبلكس الكبيرة أو المباني متعددة الطوابق',
                        'تشمل أكثر من 5 غرف، كنس السلالم، والشرفات',
                        'تنظيف كامل لسطح المبنى وغسيل النوافذ الخارجية مع التلميع'
                    ],
                    'details_en' => [
                        'Covers large duplexes and multi-story buildings',
                        'Includes 5+ rooms, staircase sweeping, and balconies',
                        'Complete roof boundary clearing & external window wash tint'
                    ],
                ]);

            } 
            // Otherwise apply the automotive caravan tracking configuration details layout
            elseif ($service->category_id === $carWash?->id || $service->company?->category_id === $carWash?->id) {
                
                // Package 1: Small Sedan Frame
                Package::create([
                    'service_id' => $service->id,
                    'name_ar' => 'فئة السيدان والهاتشباك',
                    'name_en' => 'Sedan / Hatchback Tier',
                    'duration' => 60,
                    'price' => 24,
                    'price_after_discount' => $service->discount > 0 ? (24 - (24 * ($service->discount/100))) : 24,
                    'details_ar' => [
                        'مخصصة للسيارات الصغيرة وسيارات الركوب اليومية الكومبكت',
                        'تنظيف وشفط الغبار من مقصورة السيارة المكونة من 4 مقاعد شائعة',
                        'مسح لوحة القيادة (التابلوه) وتلميع الإطارات برذاذ مخصص'
                    ],
                    'details_en' => [
                        'Optimized for compact passenger vehicles & daily sedans',
                        'Standard 4-seat matching cabin vacuuming',
                        'Dashboard wipe down & tire gloss polish spray'
                    ],
                ]);

                // Package 2: Standard SUV Setup
                Package::create([
                    'service_id' => $service->id,
                    'name_ar' => 'فئة السيارات العائلية والكروس أوفر (SUV)',
                    'name_en' => 'SUV & Crossover Tier',
                    'duration' => 120,
                    'price' => 60,
                    'price_after_discount' => $service->discount > 0 ? 60 - (60 * ($service->discount/100)) : 60,
                    'details_ar' => [
                        'مصممة خصيصاً لسيارات الكروس أوفر والسيارات العائلية التي تتسع لـ 5 إلى 7 ركاب',
                        'تشمل إزالة الأوساخ والأتربة من صندوق الأمتعة الخلفي للمركبة',
                        'غسيل الأرضيات وتطهير فتحات التكييف بعمق للتخلص من الروائح الكريهة'
                    ],
                    'details_en' => [
                        'Tailored specifically for 5 to 7 passenger crossover vehicles',
                        'Trunk storage debris extraction included',
                        'Mat washing & deep AC vent odor neutralizer application'
                    ],
                ]);

                // Package 3: Commercial Van or Luxury Elite Truck
                Package::create([
                    'service_id' => $service->id,
                    'name_ar' => 'الفئة النخبوية للشاحنات والسيارات الفان الكبيرة',
                    'name_en' => 'Large Van & Truck Elite Tier',
                    'duration' => 90,
                    'price' => 240,
                    'price_after_discount' => $service->discount > 0 ? 240 - (240 * ($service->discount/100)) : 240,
                    'details_ar' => [
                        'مصممة لشاحنات البيك أب الكبيرة، وسيارات الدفع الرباعي الفاخرة، والفان متعددة الصفوف',
                        'معالجة وترطيب كامل للجلد أو فرك عميق للمقاعد القماشية بالبخار',
                        'تنظيف كامل وشامل لهيكل السيارة السفلي وإزالة الطين بنفاثات المياه'
                    ],
                    'details_en' => [
                        'Engineered for large pickup trucks, luxury 4x4s, and multi-row vans',
                        'Full leather treatment conditioning or heavy fabric steam scrubbing',
                        'Complete undercarriage mud jet blasting'
                    ],
                ]);
            }
        }
    }
}
