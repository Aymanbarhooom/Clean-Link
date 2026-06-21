<?php

// database/seeders/ServiceSeeder.php
namespace Database\Seeders;

use App\Models\Service;
use App\Models\Company;
use App\Models\Attribute;
use App\Models\Category;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        // Fetch our pre-seeded vendor companies
        $ecoCleanHome = Company::where('name_en', 'EcoClean Pro Solutions')->first();
        $sparkleAuto = Company::where('name_en', 'Sparkle Auto Express')->first();

        //Categories
        $homeCleaning = Category::where('name_en', 'Home')->first();
        $carWash = Category::where('name_en', 'Car')->first();

        // Fetch our pre-seeded global dictionary attributes to link below
        $extraRooms = Attribute::where('name_en', 'Number of Extra Rooms')->first();
        $extraBaths = Attribute::where('name_en', 'Number of Extra Bathrooms')->first();
        $emptyHouse = Attribute::where('name_en', 'Is the House Empty (No Furniture)?')->first();
        $postConst = Attribute::where('name_en', 'Post-Construction / Renovation Cleaning')->first();
        $fridge = Attribute::where('name_en', 'Deep Inside Fridge Cleaning')->first();

        $carSeats = Attribute::where('name_en', 'Number of Car Seats')->first();
        $bodyWax = Attribute::where('name_en', 'Exterior Body Polishing & Waxing')->first();
        $engineSteam = Attribute::where('name_en', 'Steam Engine Bay Cleaning')->first();
        $headlights = Attribute::where('name_en', 'Headlight Restoration & Polishing')->first();

        if (!$ecoCleanHome || !$sparkleAuto) {
            return;
        }

        // ==========================================================
        // 🏠 COMPANY 1: EcoClean Pro Solutions (Home Cleaning Services)
        // ==========================================================

        // Service 1: Standard Apartment Refresh
        $s1 = Service::create([
            'company_id' => $ecoCleanHome->id,
            'category_id' => $homeCleaning->id,
            'name_ar' => 'تنظيف الشقق السكنية القياسي',
            'name_en' => 'Standard Apartment Cleaning',
            'description_ar' => 'كنس ومسح وغسيل الغرف الأساسية مع تنظيف المطبخ والحمام.',
            'description_en' => 'Vacuuming, mopping, and dusting of living spaces including standard kitchen and bathroom wash.',
            'rating' => 4.20,
            'min_duration' => 120,
            'max_duration' => 180,
            'price' => 50.00,
            'image' => 'services/standard_apartment.jpg',
            'discount' => 0.00,
        ]);
        // Attach localized flexible dynamic addon rules for this explicit service
        $s1->attributes()->attach([
            $extraRooms->id => ['price' => 15.00, 'duration' => 30],
            $extraBaths->id => ['price' => 20.00, 'duration' => 45],
            $fridge->id => ['price' => 10.00, 'duration' => 20]
        ]);

        // Service 2: Deep Sanitization Package
        $s2 = Service::create([
            'company_id' => $ecoCleanHome->id,
            'category_id' => $homeCleaning->id,
            'name_ar' => 'خدمة التنظيف العميق والتعقيم',
            'name_en' => 'Deep Sanitization Care',
            'description_ar' => 'تنظيف شامل ومكثف يشمل إزالة الدهون المستعصية وتطهير الأسطح والمفروشات.',
            'description_en' => 'Intensive surface scrub focusing on grime removal, absolute sanitization, and heavy dusting.',
            'rating' => 4.90,
            'min_duration' => 240,
            'max_duration' => 360,
            'price' => 120.00,
            'image' => 'services/deep_home.jpg',
            'discount' => 0.00,
        ]);
        $s2->attributes()->attach([
            $extraRooms->id => ['price' => 25.00, 'duration' => 45],
            $extraBaths->id => ['price' => 35.00, 'duration' => 60],
            $emptyHouse->id => ['price' => -20.00, 'duration' => -30] // Discounted because empty houses clean faster!
        ]);

        // Service 3: Post-Construction Wiping
        $s3 = Service::create([
            'company_id' => $ecoCleanHome->id,
            'category_id' => $homeCleaning->id,
            'name_ar' => 'تنظيف المنازل ما بعد الطلاء والترميم',
            'name_en' => 'Post-Renovation Cleaning Service',
            'description_ar' => 'إزالة بقايا الطلاء، الاسمنت، الأتربة الكثيفة وتلميع السيراميك بعد أعمال البناء.',
            'description_en' => 'Removal of industrial paint drops, heavy concrete dust, and detailed glass wiping after builder handovers.',
            'rating' => 3.80,
            'min_duration' => 300,
            'max_duration' => 480,
            'price' => 200.00,
            'image' => 'services/post_con.jpg',
            'discount' => 20.00,
        ]);
        $s3->attributes()->attach([
            $postConst->id => ['price' => 50.00, 'duration' => 90],
            $extraRooms->id => ['price' => 30.00, 'duration' => 60]
        ]);

        // Service 4: Premium Kitchen Deep Clean
        $s4 = Service::create([
            'company_id' => $ecoCleanHome->id,
            'category_id' => $homeCleaning->id,
            'name_ar' => 'تنظيف وتعقيم المطابخ الاحترافي',
            'name_en' => 'Premium Kitchen Deep Clean',
            'description_ar' => 'تفكيك فلاتر الشفاطات، غسيل الخزائن من الداخل والخارج وإزالة حروق الدهون من الأفران.',
            'description_en' => 'Hood filter degreasing, internal cupboard scrub, and intensive oven carbon cleaning.',
            'rating' => 4.50,
            'min_duration' => 90,
            'max_duration' => 150,
            'price' => 45.00,
            'image' => 'services/kitchen.jpg',
            'discount' => 0.00,
        ]);
        $s4->attributes()->attach([
            $fridge->id => ['price' => 12.00, 'duration' => 30]
        ]);

        // Service 5: Elite Villa Cleanup Match
        $s5 = Service::create([
            'company_id' => $ecoCleanHome->id,
            'category_id' => $homeCleaning->id,
            'name_ar' => 'الخدمة الملكية لتنظيف الفيلات والمساحات الواسعة',
            'name_en' => 'Elite Villa Cleanup',
            'description_ar' => 'فريق متكامل مجهز بأحدث الآلات لتنظيف الفيلات الفاخرة متعددة الطوابق بالكامل.',
            'description_en' => 'Dedicated multi-worker crew deployment with advanced machines for multi-story mansion care.',
            'rating' => 4.70,
            'min_duration' => 360,
            'max_duration' => 600,
            'price' => 350.00,
            'image' => 'services/villa.jpg',
            'discount' => 0.00,
        ]);
        $s5->attributes()->attach([
            $extraRooms->id => ['price' => 40.00, 'duration' => 60],
            $extraBaths->id => ['price' => 50.00, 'duration' => 60]
        ]);


        // ==========================================================
        // 🚗 COMPANY 2: Sparkle Auto Express (Car Wash Services)
        // ==========================================================

        // Service 6: Quick Outer Eco Wash
        $s6 = Service::create([
            'company_id' => $sparkleAuto->id,
            'category_id' => $carWash->id,
            'name_ar' => 'الغسيل السريع الخارجي الصديق للبيئة',
            'name_en' => 'Quick Eco Exterior Wash',
            'description_ar' => 'تنظيف خارجي سريع بهيدرو-بخار مع تلميع الإطارات في موقعك.',
            'description_en' => 'Express hydro-steam exterior body wash topped with tire shine sealant at your location.',
            'rating' => 4.10,
            'min_duration' => 30,
            'max_duration' => 45,
            'price' => 15.00,
            'image' => 'services/eco_wash.jpg',
            'discount' => 10.00,
        ]);
        $s6->attributes()->attach([
            $bodyWax->id => ['price' => 10.00, 'duration' => 20]
        ]);

        // Service 7: Full Interior & Exterior Detail
        $s7 = Service::create([
            'company_id' => $sparkleAuto->id,
            'category_id' => $carWash->id,
            'name_ar' => 'غسيل وتفصيل داخلي وخارجي متكامل',
            'name_en' => 'Full Interior & Exterior Care',
            'description_ar' => 'كنس عميق، تنظيف التابلو، غسيل الديكورات الداخلية بالإضافة للغسيل الخارجي الكامل.',
            'description_en' => 'Vacuum tracking, dashboard treatment, door card conditioning, and full exterior detailing.',
            'rating' => 4.80,
            'min_duration' => 60,
            'max_duration' => 90,
            'price' => 35.00,
            'image' => 'services/full_car.jpg',
            'discount' => 0.00,
        ]);
        $s7->attributes()->attach([
            $carSeats->id => ['price' => 5.00, 'duration' => 15], // Price calculated per extra seat
            $bodyWax->id => ['price' => 15.00, 'duration' => 25],
            $engineSteam->id => ['price' => 20.00, 'duration' => 30]
        ]);

        // Service 8: Executive Fabric & Leather Steam
        $s8 = Service::create([
            'company_id' => $sparkleAuto->id,
            'category_id' => $carWash->id,
            'name_ar' => 'تنظيف وتطهير مقاعد ومقود السيارة بالبخار',
            'name_en' => 'Executive Upholstery Steam Extract',
            'description_ar' => 'سحب وإزالة البقع الصعبة من المقاعد المخملية أو ترطيب وتلميع المقاعد الجلدية.',
            'description_en' => 'Hot-water extraction for velvet fabrics or advanced conditioning formulas for fine leathers.',
            'rating' => 4.60,
            'min_duration' => 90,
            'max_duration' => 120,
            'price' => 60.00,
            'image' => 'services/car_steam.jpg',
            'discount' => 0.00,
        ]);
        $s8->attributes()->attach([
            $carSeats->id => ['price' => 8.00, 'duration' => 20]
        ]);

        // Service 9: Optical Headlight Restoration
        $s9 = Service::create([
            'company_id' => $sparkleAuto->id,
            'category_id' => $carWash->id,
            'name_ar' => 'تلميع وصيانة المصابيح الأمامية الباهتة',
            'name_en' => 'Optical Headlight Restoration',
            'description_ar' => 'إزالة غشاوة الاصفرار الناتجة عن الشمس واستعادة شفافية زجاج الأضواء بالكامل.',
            'description_en' => 'Oxidization scraping and multi-stage polishing to restore complete headlight clarity.',
            'rating' => 3.50,
            'min_duration' => 40,
            'max_duration' => 60,
            'price' => 25.00,
            'image' => 'services/headlights.jpg',
            'discount' => 5.00,
        ]);
        $s9->attributes()->attach([
            $headlights->id => ['price' => 0.00, 'duration' => 0]
        ]);

        $s10 = Service::create([
            'company_id' => $sparkleAuto->id, 
            'category_id' => $carWash->id,
            'name_ar' => 'تفصيل صالة العرض الشامل (تجديد السيارة بالكامل)', 
            'name_en' => 'Ultimate Showroom Detailing Master', 
            'description_ar' => 'الخدمة القصوى للسيارات: تنظيف بخار للمحرك، تفصيل المقصورة، بولش خشن وناعم لإزالة الخدوش.', 
            'description_en' => 'The absolute highest-tier care: deep steam extraction, multi-stage machine scratch removal, and undercarriage jet wash.', 
            'rating' => 4.95, 
            'min_duration' => 180, 
            'max_duration' => 300, 
            'price' => 150.00, 
            'image' => 'services/showroom.jpg', 
            'discount' => 0.00,]);
        $s10->attributes()->attach([
            $bodyWax->id => ['price' => 0.00, 'duration' => 0],
            $engineSteam->id => ['price' => 0.00, 'duration' => 0],
            $carSeats->id => ['price' => 6.00, 'duration' => 10]
        ]);
    }
}
