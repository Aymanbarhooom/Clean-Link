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
                'manager_id' => $managers[0]->id,
            ]);

            Region::create([
                'name_ar' => 'حلب',
                'name_en' => 'Aleppo',
                'manager_id' => $managers[1]->id,
            ]);
            Region::create([
                'name_ar' => 'حمص',
                'name_en' => 'Homs',
                'manager_id' => $managers[0]->id,
            ]);
            Region::create([
                'name_ar' => 'اللاذقية',
                'name_en' => 'Latakia',
                'manager_id' => $managers[1]->id,
            ]);
        }
    }
}
