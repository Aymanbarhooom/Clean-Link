<?php

// database/seeders/AttributeSeeder.php
namespace Database\Seeders;

use App\Models\AttributeModel;
use Illuminate\Database\Seeder;

class AttributeSeeder extends Seeder
{
    public function run(): void
    {
        $attributes = [
            // --- 🏠 Home & Residential Cleaning Attributes ---
            [
                'name_ar' => 'عدد الغرف الإضافية',
                'name_en' => 'Number of Extra Rooms',
                'type' => 'number',
            ],
            [
                'name_ar' => 'عدد المسابح',
                'name_en' => 'Number of Swimming Pools',
                'type' => 'number',
            ],
            [
                'name_ar' => 'عددالأشجار',
                'name_en' => 'Number of Extra trees',
                'type' => 'number',
            ],
            [
                'name_ar' => 'تنظيف السطح',
                'name_en' => 'Exterior Surface Cleaning',
                'type' => 'boolean',
            ],
            [
                'name_ar' => 'عدد المكاتب الإضافية',
                'name_en' => 'Number of Extra Offices',
                'type' => 'number',
            ],
            [
                'name_ar' => 'عدد الحمامات الإضافية',
                'name_en' => 'Number of Extra Bathrooms',
                'type' => 'number',
            ],
            [
                'name_ar' => 'عدد المطابخ',
                'name_en' => 'Number of Kitchens',
                'type' => 'number',
            ],
            [
                'name_ar' => 'عدد طوابق المبنى',
                'name_en' => 'Number of Floors',
                'type' => 'number',
            ],
            [
                'name_ar' => 'عدد الشرفات (البلكونات)',
                'name_en' => 'Number of Balconies',
                'type' => 'number',
            ],
            [
                'name_ar' => 'الطول',
                'name_en' => 'Length',
                'type' => 'number',
            ],
            [
                'name_ar' => 'العرض',
                'name_en' => 'Width',
                'type' => 'number',
            ],
            [
                'name_ar' => 'الارتفاع',
                'name_en' => 'Height',
                'type' => 'number',
            ],
            [
                'name_ar' => 'مساحة الأرضية بالمتر المربع',
                'name_en' => 'Floor Area in Square Meters',
                'type' => 'number',
            ],
            [
                'name_ar' => 'مساحة السطح بالمتر المربع',
                'name_en' => 'Roof Area in Square Meters',
                'type' => 'number',
            ],
            [
                'name_ar' => 'عدد القمصان',
                'name_en' => 'Number of Extra Shirts',
                'type' => 'number',
            ],
            [
                'name_ar' => 'عدد الملابس الداخلية',
                'name_en' => 'Number of Extra Underwear',
                'type' => 'number',
            ],
            [
                'name_ar' => 'عدد البناطيل',
                'name_en' => 'Number of Extra Pants',
                'type' => 'number',
            ],
            [
                'name_ar' => 'مساحة الحديقة بالمتر المربع',
                'name_en' => 'Garden Area in Square Meters',
                'type' => 'number',
            ],
            [
                'name_ar' => 'تنظيف وتلميع النوافذ الخارجية',
                'name_en' => 'Exterior Window Cleaning',
                'type' => 'boolean',
            ],
            [
                'name_ar' => 'تنظيف عميق لداخل الثلاجة',
                'name_en' => 'Deep Inside Fridge Cleaning',
                'type' => 'boolean',
            ],
            [
                'name_ar' => 'تنظيف عميق لداخل الفرن',
                'name_en' => 'Deep Inside Oven Cleaning',
                'type' => 'boolean',
            ],
            [
                'name_ar' => 'هل المنزل فارغ من الأثاث؟',
                'name_en' => 'Is the House Empty (No Furniture)?',
                'type' => 'boolean',
            ],
            [
                'name_ar' => 'تنظيف ما بعد البناء والترميم',
                'name_en' => 'Post-Construction / Renovation Cleaning',
                'type' => 'boolean',
            ],

            // --- 🚗 Auto & Mobile Vehicle Detailing Attributes ---
            [
                'name_ar' => 'حجم السيارة (صغيرة / صالون / عائلية كبيرة)',
                'name_en' => 'Vehicle Size (Sedan / SUV / Large van)',
                'type' => 'text', // Standardized scaling choice text entry
            ],
            [
                'name_ar' => 'عدد السيارات الإضافية',
                'name_en' => 'Number of Extra Cars',
                'type' => 'number',
            ],
            [
                'name_ar' => 'عدد ركاب السيارة / المقاعد',
                'name_en' => 'Number of Car Seats',
                'type' => 'number',
            ],
            [
                'name_ar' => 'تلميع ساطع بالبوليش والشمع (Wax)',
                'name_en' => 'Exterior Body Polishing & Waxing',
                'type' => 'boolean',
            ],
            [
                'name_ar' => 'غسيل وتطهير المحرك بالبخار',
                'name_en' => 'Steam Engine Bay Cleaning',
                'type' => 'boolean',
            ],
            [
                'name_ar' => 'تنظيف وإزالة بقع صندوق السيارة (الطبون)',
                'name_en' => 'Trunk Vacuuming & Deep Cleaning',
                'type' => 'boolean',
            ],
            [
                'name_ar' => 'تلميع المصابيح الأمامية (الاضواء)',
                'name_en' => 'Headlight Restoration & Polishing',
                'type' => 'boolean',
            ],

            // --- 🛋️ Furniture, Carpets & Fabric Upholstery Attributes ---
            [
                'name_ar' => 'عدد السجاد المراد غسيله',
                'name_en' => 'Number of Carpets / Rugs',
                'type' => 'number',
            ],
            [
                'name_ar' => 'عدد مقاعد الكنب (الصوفا)',
                'name_en' => 'Number of Sofa Seats',
                'type' => 'number',
            ],
            [
                'name_ar' => 'نوع قماش الأثاث (جلد / مخمل / حرير)',
                'name_en' => 'Fabric Type (Leather / Velvet / Silk)',
                'type' => 'text',
            ],
            [
                'name_ar' => 'تنظيف مراتب السرير (دوشك)',
                'name_en' => 'Number of Bed Mattresses',
                'type' => 'number',
            ],
            [
                'name_ar' => 'غسيل وتعقيم الستائر بالبخار وهي معلقة',
                'name_en' => 'Steam Curtain Cleaning (Hanging)',
                'type' => 'number', // Per curtain item unit calculation
            ],

            // --- 🏢 Commercial, Offices & Facilities Attributes ---
            [
                'name_ar' => 'عدد المكاتب / الطاولات الإضافية',
                'name_en' => 'Number of Extra Desks / Workstations',
                'type' => 'number',
            ],
            [
                'name_ar' => 'عدد غرف الاجتماعات',
                'name_en' => 'Number of Meeting / Conference Rooms',
                'type' => 'number',
            ],
            [
                'name_ar' => 'تفريغ حاويات النفايات الكبيرة وإعادة التدوير',
                'name_en' => 'Bulk Waste Emptying & Recycling Disposal',
                'type' => 'boolean',
            ],

            // --- 🏊 Yard, Pools & Outdoor Property Attributes ---
            [
                'name_ar' => 'تنظيف وتعقيم حوض السباحة (المسبح)',
                'name_en' => 'Swimming Pool Cleaning & Balancing',
                'type' => 'boolean',
            ],
            [
                'name_ar' => 'تنظيف غسيل الحوش والحديقة الخارجية بالضغط العالي',
                'name_en' => 'High-Pressure Backyard & Patio Jet Wash',
                'type' => 'boolean',
            ],
            [
                'name_ar' => 'تنظيف واجهات المباني الزجاجية المرتفعة',
                'name_en' => 'High-Rise Corporate Glass Facade Cleaning',
                'type' => 'boolean',
            ],
            [
                'name_ar' => 'تنظيف وتنقية خزانات المياه الأرضية أو العلوية',
                'name_en' => 'Water Tank Disinfection & Cleaning',
                'type' => 'boolean',
            ],

            // --- 🧼 Specialized & Premium Addons ---
            [
                'name_ar' => 'تطهير وتعقيم كامل ضد الفيروسات والبكتيريا',
                'name_en' => 'Complete Anti-Viral & Bacterial Sanitization Fogging',
                'type' => 'boolean',
            ],
            [
                'name_ar' => 'إزالة روائح الحيوانات الأليفة والتدخين العميقة (أوزون)',
                'name_en' => 'Deep Odor / Pet Scent Elimination (Ozone Treatment)',
                'type' => 'boolean',
            ],
            [
                'name_ar' => 'جلب مواد تنظيف عضوية / مضادة للحساسية',
                'name_en' => 'Eco-Friendly / Hypoallergenic Cleaning Supplies Request',
                'type' => 'boolean',
            ]
        ];

        foreach ($attributes as $attribute) {
            AttributeModel::create($attribute);
        }
    }
}
