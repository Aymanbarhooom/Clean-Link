<?php

// database/seeders/CategorySeeder.php
namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
{
    $categories = [
        [
            'name_ar' => 'البيوت',
            'name_en' => 'Home',
            'description_ar' => 'خدمات تنظيف شاملة للمنازل والفيلات والشقق السكنية.',
            'description_en' => 'Comprehensive sanitization services for houses, villas, and apartments.',
            'image' => 'categories/home_cleaning.png',
        ],
        [
            'name_ar' => 'السيارات',
            'name_en' => 'Car',
            'description_ar' => 'خدمات تنظيف وغسيل سيارات متنقلة بدقة عالية.',
            'description_en' => 'Mobile eco-friendly car washing and professional detailing.',
            'image' => 'categories/car_wash.png',
        ],
        [
            'name_ar' => 'المكاتب',
            'name_en' => 'Office',
            'description_ar' => 'تنظيف وتعقيم دوري للمكاتب ومساحات العمل التجارية.',
            'description_en' => 'Regular sanitization and cleaning for offices and commercial workspaces.',
            'image' => 'categories/office_cleaning.png',
        ],
        [
            'name_ar' => 'السجاد والمفروشات',
            'name_en' => 'Carpets & Upholstery',
            'description_ar' => 'تنظيف عميق وإزالة البقع من السجاد والمجالس والكنب.',
            'description_en' => 'Deep cleaning and stain removal for carpets, sofas, and upholstery.',
            'image' => 'categories/carpet_cleaning.png',
        ],
        [
            'name_ar' => 'المكيفات',
            'name_en' => 'Air Conditioners',
            'description_ar' => 'خدمات تنظيف وصيانة فلاتر وأجهزة التكييف لضمان هواء نقي.',
            'description_en' => 'Professional cleaning and maintenance for AC units and filters.',
            'image' => 'categories/ac_cleaning.png',
        ],
        [
            'name_ar' => 'خزانات المياه',
            'name_en' => 'Water Tanks',
            'description_ar' => 'تعقيم وتنظيف خزانات المياه لضمان سلامة المياه للاستخدام.',
            'description_en' => 'Sanitization and cleaning services for water tanks.',
            'image' => 'categories/tank_cleaning.png',
        ],
        [
            'name_ar' => 'المسابح',
            'name_en' => 'Swimming Pools',
            'description_ar' => 'تنظيف دوري ومعالجة مياه المسابح لضمان نظافتها.',
            'description_en' => 'Routine cleaning and chemical treatment for swimming pools.',
            'image' => 'categories/pool_cleaning.png',
        ],
        [
            'name_ar' => 'بعد البناء',
            'name_en' => 'Post-Construction',
            'description_ar' => 'تنظيف شامل للمباني بعد انتهاء أعمال البناء والترميم.',
            'description_en' => 'Thorough cleaning services after construction and renovation works.',
            'image' => 'categories/post_construction.png',
        ],
        [
            'name_ar' => 'واجهات زجاجية',
            'name_en' => 'Glass Facades',
            'description_ar' => 'تنظيف وتلميع الواجهات الزجاجية للمباني العالية.',
            'description_en' => 'Professional cleaning and polishing of high-rise glass facades.',
            'image' => 'categories/glass_cleaning.png',
        ],
        [
            'name_ar' => 'تعقيم وتطهير',
            'name_en' => 'Sanitization',
            'description_ar' => 'خدمات تعقيم متخصصة للمساحات ضد الفيروسات والبكتيريا.',
            'description_en' => 'Specialized sanitization services against viruses and bacteria.',
            'image' => 'categories/sanitization.png',
        ],
    ];

    foreach ($categories as $category) {
        Category::create($category);
    }
}

}
