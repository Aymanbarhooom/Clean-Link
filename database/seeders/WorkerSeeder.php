<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Profile;
use App\Models\Company;
use App\Models\WorkerProfile;
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
        // 1. Fetch your 10 existing companies
        $companies = Company::take(10)->get();

        if ($companies->count() < 10) {
            $this->command->error('You need at least 10 companies in the database before running this seeder.');
            return;
        }

        // 2. Define base names & generate permutations
        $baseNames = ["Ahmed", "Fatima", "Youssef", "Maryam", "Omar", "Zainab", "Ali", "Khadija", "Khaled", "Aisha", "Mustafa"];
        $fullNames = [];

        foreach ($baseNames as $first) {
            foreach ($baseNames as $last) {
                if ($first !== $last) {
                    $fullNames[] = "$first $last";
                }
            }
        }

        // 3. Keep exactly 100 names as requested
        $fullNames = array_slice($fullNames, 0, 100);

        // 4. Counter to track position in our 100-name list
        $nameIndex = 0;

        // 5. Assign 10 workers to each of the 10 companies
        foreach ($companies as $company) {
            for ($i = 0; $i < 10; $i++) {
                $currentFullName = $fullNames[$nameIndex];
                
                // Convert full name to a unique slug format for their email (e.g., ahmed.fatima@example.com)
                $emailPrefix = Str::slug($currentFullName, '.');
                $uniqueEmail = $emailPrefix . '.' . rand(10, 99) . '@example.com';

                // Step A: Create User account (role = 'worker')
                $user = User::create([
                    'fullname' => $currentFullName,
                    'email'    => $uniqueEmail,
                    'password' => Hash::make('password123'), // Secure default password
                    'role'     => 'worker',
                ]);

                // Step B: Create default profile linked to user
                Profile::create([
                    'user_id' => $user->id,
                    'image'   => null, // Handled by your model's null/storage asset accessor
                    'address' => 'Street ' . rand(1, 100) . ', Cairo, Egypt',
                    'phone'   => '+201' . rand(0, 2) . rand(10000000, 99999999),
                ]);

                // Step C: Create Worker profile linking user to current company
                WorkerProfile::create([
                    'user_id'          => $user->id,
                    'company_id'       => $company->id,
                    'experience_years' => rand(1, 15),
                    'rating'           => rand(30, 50) / 10, 
                ]);

                $nameIndex++;
            }
        }

        $this->command->info('Successfully seeded 100 workers distributed across 10 companies!');
    }
}
