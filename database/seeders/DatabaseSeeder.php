<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\Skill;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            RegionSeeder::class,
            CategorySeeder::class,
            CompanySeeder::class,
            WorkTimeSeeder::class,
            AttributeSeeder::class,
            SkillSeeder::class,
            ServiceSeeder::class,
            PackageSeeder::class,
            ServiceImageSeeder::class,
            ReviewSeeder::class,
            WorkerSeeder::class
        ]);
    }
}
