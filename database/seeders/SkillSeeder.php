<?php

// database/seeders/SkillSeeder.php
namespace Database\Seeders;

use App\Models\Skill;
use Illuminate\Database\Seeder;

class SkillSeeder extends Seeder
{
    public function run(): void
    {
        $skills = [
            // --- 🏠 Home & Residential Specialized Skills ---
            [
                'name_ar' => 'التنظيف السكني القياسي',
                'name_en' => 'Standard Residential Cleaning',
            ],
            [
                'name_ar' => 'التنظيف العميق وإزالة الدهون المستعصية',
                'name_en' => 'Deep Cleaning & Degreasing Operations',
            ],
            [
                'name_ar' => 'تنظيف وتعقيم المطابخ الاحترافي',
                'name_en' => 'Professional Kitchen Sanitation',
            ],
            [
                'name_ar' => 'تعقيم وتطهير الحمامات والمسطحات البيضاء',
                'name_en' => 'Advanced Bathroom Disinfection',
            ],
            [
                'name_ar' => 'تنظيف وإزالة مخلفات البناء والطلاء ما بعد الترميم',
                'name_en' => 'Post-Construction / Renovation Cleanup',
            ],
            [
                'name_ar' => 'تلميع وجلي الأرضيات (رخام، سيراميك، باركيه)',
                'name_en' => 'Floor Buffing, Polishing & Polishing Machines',
            ],
            [
                'name_ar' => 'تنظيف النوافذ الخارجية والواجهات الزجاجية',
                'name_en' => 'Window Washing & Exterior Glass Wiping',
            ],

            // --- 🛋️ Fabric Upholstery, Curtains & Rug Care Skills ---
            [
                'name_ar' => 'غسيل وتنظيف السجاد بآلات السحب والضغط',
                'name_en' => 'Carpet & Rug Hot-Water Extraction',
            ],
            [
                'name_ar' => 'تنظيف الكنب والأثاث القماشي بالبخار الجاف',
                'name_en' => 'Dry Steam Sofa & Furniture Cleaning',
            ],
            [
                'name_ar' => 'معالجة وتنظيف الأثاث الجلدي الفاخر وترطيبه',
                'name_en' => 'Fine Leather Care & Conditioning',
            ],
            [
                'name_ar' => 'غسيل وتعقيم الستائر الرأسية والأفقية المعلقة',
                'name_en' => 'Hanging Curtain Steam Cleansing',
            ],

            // --- 🚗 Automotive Care & Detailing Specialist Skills ---
            [
                'name_ar' => 'غسيل وتلميع السيارات الخارجي (الهيدرو-بخار)',
                'name_en' => 'Exterior Hydro-Steam Vehicle Washing',
            ],
            [
                'name_ar' => 'التنظيف الداخلي العميق لقصور ومقاعد السيارات',
                'name_en' => 'Detailed Automotive Interior Vacuuming & Wiping',
            ],
            [
                'name_ar' => 'تلميع هيكل السيارة وتصحيح الطلاء بالبولش والشمع',
                'name_en' => 'Paint Correction & Machine Polishing / Waxing',
            ],
            [
                'name_ar' => 'غسيل وتنظيف محركات السيارات بالبخار الحراري',
                'name_en' => 'Thermal Steam Engine Bay Detailing',
            ],
            [
                'name_ar' => 'تجديد وصيانة المصابيح الأمامية الباهتة (صنفرة وبولش)',
                'name_en' => 'Headlight Acrylic Restoration & Clarity Polishing',
            ],

            // --- 🏊 Commercial Property & Premium Addon Skills ---
            [
                'name_ar' => 'تنظيف وتعقيم المكاتب والمساحات المشتركة للشركات',
                'name_en' => 'Commercial Office & Corporate Cleaning',
            ],
            [
                'name_ar' => 'تنظيف وصيانة فلاتر وأحواض السباحة',
                'name_en' => 'Swimming Pool Maintenance & Chemical Balancing',
            ],
            [
                'name_ar' => 'التنقية والتعقيم بجهاز رذاذ مكافحة الفيروسات (Fogging)',
                'name_en' => 'Anti-Viral/Bacterial Space Fogging & Disinfection',
            ],
            [
                'name_ar' => 'إزالة الروائح العميقة بمعالجة غاز الأوزون والتطهير البيئي',
                'name_en' => 'Ozone Air Purifying & Deep Scent Elimination',
            ],
            [
                'name_ar' => 'تنظيف وتعقيم خزانات المياه الأرضية والعلوية للمباني',
                'name_en' => 'Water Tank Scouring & Disinfection Procedures',
            ]
        ];

        foreach ($skills as $skill) {
            Skill::create($skill);
        }
    }
}
