<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Profile;
use App\Models\Company;
use App\Models\WorkerProfile;
use App\Models\Skill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class WorkerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::take(10)->get();

        if ($companies->count() < 10) {
            $this->command->error('You need at least 10 companies in the database before running this seeder.');
            return;
        }

        $baseNames = ["Ahmed", "Ali", "Youssef", "Bakr", "Omar", "Oday", "Osama", "Amr", "Khaled", "Sami", "Mustafa"];
        $fullNames = [];

        foreach ($baseNames as $first) {
            foreach ($baseNames as $last) {
                if ($first !== $last) {
                    $fullNames[] = "$first $last";
                }
            }
        }

        $fullNames = array_slice($fullNames, 0, 100);

        $workerImagePaths = [
            'worker_profiles/worker1.jpg',
            'worker_profiles/worker2.jpg',
            'worker_profiles/worker3.jpg',
            'worker_profiles/worker4.jpg',
            'worker_profiles/worker5.jpg',
            'worker_profiles/worker6.jpg',
            'worker_profiles/worker7.jpg',
            'worker_profiles/worker8.jpg',
            'worker_profiles/worker9.jpg',
            'worker_profiles/worker10.jpg',
        ];

        $nameIndex = 0;

        foreach ($companies as $company) {
           $this->command->info("Adding $company->id company workers distributed across 10 companies!");
            for ($i = 0; $i < 10; $i++) {
                $currentFullName = $fullNames[$nameIndex];
                
                $emailPrefix = Str::slug($currentFullName, '.');
                $uniqueEmail = $emailPrefix . '@example.com';

                $user = User::create([
                    'fullname' => $currentFullName,
                    'email'    => $uniqueEmail,
                    'password' => Hash::make('password123'), 
                    'role'     => 'worker',
                ]);

                Profile::create([
                    'user_id' => $user->id,
                    'image'   => $workerImagePaths[$nameIndex % count($workerImagePaths)],
                    'address' => 'Street ' . rand(1, 100) . ', Cairo, Egypt',
                    'phone'   => '+201' . rand(0, 2) . rand(10000000, 99999999),
                ]);

                $workerProfile = WorkerProfile::create([
                    'user_id'          => $user->id,
                    'company_id'       => $company->id,
                    'experience_years' => rand(1, 15),
                    'rating'           => rand(30, 50) / 10, 
                ]);

                $allSkills = Skill::take(7)->get();
                $allSkills = $allSkills->pluck('id')->toArray();
                if (!empty($allSkills)) {
                    $randomSkills = array_slice($allSkills, 0, min(4, count($allSkills)));
                    if (count($randomSkills) < 4) {
                        $randomSkills = $allSkills;
                    } else {
                        shuffle($allSkills);
                        $randomSkills = array_slice($allSkills, 0, 4);
                    }
                    $workerProfile->skills()->attach($randomSkills);
                }

                $nameIndex++;
            }
        }

        $this->command->info('Successfully seeded 100 workers distributed across 10 companies!');
    }
}
