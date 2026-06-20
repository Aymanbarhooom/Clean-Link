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
                'name_ar' => 'المنطقة الشمالية',
                'name_en' => 'North Region',
                'manager_id' => $managers[0]->id,
            ]);

            Region::create([
                'name_ar' => 'المنطقة الجنوبية',
                'name_en' => 'South Region',
                'manager_id' => $managers[1]->id,
            ]);
        }
    }
}
