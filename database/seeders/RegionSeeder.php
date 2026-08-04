<?php

// database/seeders/RegionSeeder.php
namespace Database\Seeders;

use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $managers = User::where('role', 'region_manager')->get();

        if ($managers->count() >= 2) {
            Region::create([
                'name_ar' => 'دمشق',
                'name_en' => 'Damascus',
                'manager_id' => 1,
                'image' => 'regions/damascus.jpg'
            ]);

            Region::create([
                'name_ar' => 'حلب',
                'name_en' => 'Aleppo',
                'manager_id' => $managers[1]->id,
                'image' => 'regions/aleppo.jpg'
            ]);
            Region::create([
                'name_ar' => 'حمص',
                'name_en' => 'Homs',
                'manager_id' => 1,
                'image' => 'regions/homs.jpg'
            ]);
            Region::create([
                'name_ar' => 'اللاذقية',
                'name_en' => 'Latakia',
                'manager_id' => 1,
                'image' => 'regions/latakia.jpg'
            ]);
            Region::create([
                'name_ar' => 'حماه',
                'name_en' => 'Hama',
                'manager_id' => 1,
                'image' => 'regions/hama.jpg'
            ]);
            Region::create([
                'name_ar' => 'طرطوس',
                'name_en' => 'Tartus',
                'manager_id' => 1,
                'image' => 'regions/tartus.jpg'
            ]);
        }
    }
}
