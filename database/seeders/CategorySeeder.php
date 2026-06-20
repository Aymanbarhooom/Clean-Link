<?php

// database/seeders/CategorySeeder.php
namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        Category::create([
            'name_ar' => 'تنظيف المنازل والبيوت',
            'name_en' => 'Home Cleaning',
            'description_ar' => 'خدمات تنظيف شاملة للمنازل والفيلات والشقق السكنية المخصصة.',
            'description_en' => 'Comprehensive sanitization services for houses, villas, and apartments.',
            'image' => 'categories/home_cleaning.png',
        ]);

        Category::create([
            'name_ar' => 'غسيل وتلميع السيارات',
            'name_en' => 'Car Detailing & Wash',
            'description_ar' => 'خدمات تنظيف وغسيل سيارات متنقلة عند باب منزلك بدقة عالية.',
            'description_en' => 'Mobile eco-friendly car washing and professional detailing at your doorstep.',
            'image' => 'categories/car_wash.png',
        ]);
    }
}
