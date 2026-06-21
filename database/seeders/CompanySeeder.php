<?php

// database/seeders/CompanySeeder.php
namespace Database\Seeders;

use App\Models\Company;
use App\Models\Category;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
{
    $regions = Region::all();
    $managers = User::where('role', 'company_manager')->get();

    // التأكد من وجود بيانات لتجنب الأخطاء
    if ($managers->count() < 2 || $regions->count() < 4) {
        return;
    }

    $companies = [
        [
            'manager_id' => $managers[0]->id,
            'region_id' => $regions[0]->id,
            'name_ar' => 'إيكو كلين للمقاولات والتنظيف',
            'name_en' => 'EcoClean Pro Solutions',
            'description_ar' => 'شركة متخصصة في التنظيف الصديق للبيئة والتعقيم المنزلي الفاخر.',
            'description_en' => 'Specialized agency focusing on eco-friendly sanitization and premium home care.',
            'image' => 'companies/ecoclean.jpg',
            'location_ar' => 'دمشق - المزة',
            'location_en' => 'Damascus - Mazzeh',
            'rating' => 5.00,
            'is_open' => true,
            'start_hour' => '08:00',
            'close_hour' => '22:00',
        ],
        [
            'manager_id' => $managers[1]->id,
            'region_id' => $regions[1]->id,
            'name_ar' => 'سباركل إكسبريس المتنقلة',
            'name_en' => 'Sparkle Auto Express',
            'description_ar' => 'أسرع خدمة غسيل سيارات متنقلة مجهزة بأحدث أدوات البخار.',
            'description_en' => 'Fast mobile car wash caravans equipped with advanced steam equipment.',
            'image' => 'companies/sparkle.jpg',
            'location_ar' => 'حلب - الشهباء',
            'location_en' => 'Aleppo - Al-Shahbaa',
            'rating' => 4.80,
            'is_open' => true,
            'start_hour' => '09:00',
            'close_hour' => '23:00',
        ],
        [
            'manager_id' => $managers[0]->id,
            'region_id' => $regions[2]->id,
            'name_ar' => 'شركة هوم كير بلس',
            'name_en' => 'HomeCare Plus',
            'description_ar' => 'خبراء في تنظيف السجاد والمفروشات بأحدث التقنيات الجافة.',
            'description_en' => 'Experts in carpet and upholstery cleaning using latest dry-cleaning tech.',
            'image' => 'companies/homecare.jpg',
            'location_ar' => 'حمص - جورة الشياح',
            'location_en' => 'Homs - Jouret al-Shayah',
            'rating' => 3.50, // تقييم متوسط
            'is_open' => true,
            'start_hour' => '08:00',
            'close_hour' => '20:00',
        ],
        [
            'manager_id' => $managers[1]->id,
            'region_id' => $regions[3]->id,
            'name_ar' => 'بريق الواجهات الزجاجية',
            'name_en' => 'Shine Glass Facades',
            'description_ar' => 'تنظيف احترافي للواجهات الزجاجية للأبراج والمباني التجارية.',
            'description_en' => 'Professional cleaning for glass facades of towers and commercial buildings.',
            'image' => 'companies/shine_glass.jpg',
            'location_ar' => 'اللاذقية - الكورنيش',
            'location_en' => 'Lattakia - Corniche',
            'rating' => 4.20,
            'is_open' => true,
            'start_hour' => '07:00',
            'close_hour' => '17:00',
        ],
        [
            'manager_id' => $managers[0]->id,
            'region_id' => $regions[0]->id,
            'name_ar' => 'نسيم الصيانة والتنظيف',
            'name_en' => 'Naseem AC Clean',
            'description_ar' => 'متخصصون في تنظيف وتعقيم المكيفات المركزية والمنفصلة.',
            'description_en' => 'Specialists in cleaning and sanitizing central and split AC units.',
            'image' => 'companies/naseem.jpg',
            'location_ar' => 'دمشق - مشروع دمر',
            'location_en' => 'Damascus - Dummar Project',
            'rating' => 4.90,
            'is_open' => true,
            'start_hour' => '09:00',
            'close_hour' => '21:00',
        ],
        [
            'manager_id' => $managers[1]->id,
            'region_id' => $regions[1]->id,
            'name_ar' => 'شركة النقاء للخزانات',
            'name_en' => 'Purity Tank Services',
            'description_ar' => 'تنظيف وتعقيم خزانات المياه بأفضل المواد الصحية والمعتمدة.',
            'description_en' => 'Cleaning and sanitizing water tanks using top health-certified materials.',
            'image' => 'companies/purity_tanks.jpg',
            'location_ar' => 'حلب - حي الفرقان',
            'location_en' => 'Aleppo - Al-Furqan',
            'rating' => 3.10, // تقييم قليل
            'is_open' => false, // مغلقة حالياً للتجربة
            'start_hour' => '08:00',
            'close_hour' => '16:00',
        ],
        [
            'manager_id' => $managers[0]->id,
            'region_id' => $regions[2]->id,
            'name_ar' => 'ماستر بول لتنظيف المسابح',
            'name_en' => 'Master Pool Cleaning',
            'description_ar' => 'صيانة وتعقيم المسابح الخاصة والعامة وجدولة التنظيف الدوري.',
            'description_en' => 'Maintenance and sanitization for private and public swimming pools.',
            'image' => 'companies/master_pool.jpg',
            'location_ar' => 'حمص - طريق الشام',
            'location_en' => 'Homs - Damascus Road',
            'rating' => 4.50,
            'is_open' => true,
            'start_hour' => '10:00',
            'close_hour' => '20:00',
        ],
        [
            'manager_id' => $managers[1]->id,
            'region_id' => $regions[3]->id,
            'name_ar' => 'بناء كلين لخدمات ما بعد الإنشاء',
            'name_en' => 'Binaa Clean Services',
            'description_ar' => 'إزالة مخلفات البناء والتنظيف العميق للمشاريع الجديدة.',
            'description_en' => 'Post-construction debris removal and deep cleaning for new projects.',
            'image' => 'companies/binaa_clean.jpg',
            'location_ar' => 'اللاذقية - جبلة',
            'location_en' => 'Lattakia - Jableh',
            'rating' => 4.00,
            'is_open' => true,
            'start_hour' => '07:30',
            'close_hour' => '18:00',
        ],
        [
            'manager_id' => $managers[0]->id,
            'region_id' => $regions[0]->id,
            'name_ar' => 'درع التعقيم الاحترافي',
            'name_en' => 'Pro Shield Sanitizing',
            'description_ar' => 'خدمات تعقيم شاملة للمشافي، المدارس، والمنازل.',
            'description_en' => 'Comprehensive sanitization services for hospitals, schools, and homes.',
            'image' => 'companies/pro_shield.jpg',
            'location_ar' => 'دمشق - كفرسوسة',
            'location_en' => 'Damascus - Kafr Sousa',
            'rating' => 4.75,
            'is_open' => true,
            'start_hour' => '00:00', // تعمل 24 ساعة
            'close_hour' => '23:59',
        ],
        [
            'manager_id' => $managers[1]->id,
            'region_id' => $regions[1]->id,
            'name_ar' => 'كويك أوفيس للتنظيف',
            'name_en' => 'Quick Office Clean',
            'description_ar' => 'تنظيف المكاتب والشركات بسرعة واحترافية عالية.',
            'description_en' => 'Fast and professional cleaning for offices and corporate buildings.',
            'image' => 'companies/quick_office.jpg',
            'location_ar' => 'حلب - السليمانية',
            'location_en' => 'Aleppo - Sulaymaniyah',
            'rating' => 2.90, // تقييم قليل جداً
            'is_open' => true,
            'start_hour' => '08:00',
            'close_hour' => '18:00',
        ],
    ];

    foreach ($companies as $company) {
        Company::create($company);
    }
}
}
