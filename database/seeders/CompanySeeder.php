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
        $categories = Category::all();
        $regions = Region::all();
        $managers = User::where('role', 'company_manager')->get();

        if ($categories->count() >= 2 && $regions->count() >= 2 && $managers->count() >= 2) {
            
            // Company 1: EcoClean Pro (Assigned to Home Cleaning, North Region)
            Company::create([
                'manager_id' => $managers[0]->id,
                'region_id' => $regions[0]->id,
                'name_ar' => 'إيكو كلين للمقاولات والتنظيف',
                'name_en' => 'EcoClean Pro Solutions',
                'description_ar' => 'شركة متخصصة في التنظيف الصديق للبيئة والتعقيم المنزلي الفاخر.',
                'description_en' => 'Specialized agency focusing on eco-friendly sanitization and premium home care.',
                'image' => 'companies/ecoclean.png',
                'location_ar' => 'دمشق',
                'location_en' => 'Damascus',
                'rating' => 5.00,
                'is_open' => true,
                'start_hour' => '08:00',
                'close_hour' => '22:00',
            ]);

            // Company 2: Sparkle Express (Assigned to Car Wash, South Region)
            Company::create([
                'manager_id' => $managers[1]->id,
                'region_id' => $regions[1]->id,
                'name_ar' => 'سباركل إكسبريس المتنقلة',
                'name_en' => 'Sparkle Auto Express',
                'description_ar' => 'أسرع خدمة غسيل سيارات متنقلة مجهزة بأحدث أدوات البخار والتلميع.',
                'description_en' => 'Fast mobile car wash caravans equipped with advanced steam detailing equipment.',
                'image' => 'companies/sparkle.png',
                'location_ar' => 'حلب',
                'location_en' => 'Aleppo',
                'rating' => 4.80,
                'is_open' => true,
                'start_hour' => '09:00',
                'close_hour' => '23:00',
            ]);
        }
    }
}
