<?php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Admin Account
        $admin = User::create([
            'fullname' => 'System Administrator',
            'email' => 'admin@cleaning.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);
        $admin->profile()->create(['phone' => '+96311000000', 'address' => 'HQ Main Office']);

        // 2. Region Managers
        $rm1 = User::create([
            'fullname' => 'Ahmad Region Manager',
            'email' => 'ahmad.rm@cleaning.com',
            'password' => Hash::make('password123'),
            'role' => 'region_manager',
        ]);
        $rm1->profile()->create(['phone' => '+96311111111', 'address' => 'North District Hub']);

        $rm2 = User::create([
            'fullname' => 'Sara Region Manager',
            'email' => 'sara.rm@cleaning.com',
            'password' => Hash::make('password123'),
            'role' => 'region_manager',
        ]);
        $rm2->profile()->create(['phone' => '+96311222222', 'address' => 'South District Hub']);

        // 3. Company Managers
        $cm1 = User::create([
            'fullname' => 'John Company Manager',
            'email' => 'john.cm@cleaning.com',
            'password' => Hash::make('password123'),
            'role' => 'company_manager',
        ]);
        $cm1->profile()->create(['phone' => '+96311333333', 'address' => 'EcoClean Offices']);

        $cm2 = User::create([
            'fullname' => 'Elena Company Manager',
            'email' => 'elena.cm@cleaning.com',
            'password' => Hash::make('password123'),
            'role' => 'company_manager',
        ]);
        $cm2->profile()->create(['phone' => '+96311444444', 'address' => 'Sparkle Solutions Base']);

        // 4. Clients
        $client1 = User::create([
            'fullname' => 'يعقوب قمر الدين',
            'email' => 'client1@cleaning.com',
            'password' => Hash::make('password123'),
            'role' => 'client',
        ]);
        $client1->profile()->create(['phone' => '+96311555555', 'address' => 'Client One Residence', 'image' => 'worker_profiles/worker1.jpg']);

        $client2 = User::create([
            'fullname' => 'خالد كشميري',
            'email' => 'client2@cleaning.com',
            'password' => Hash::make('password123'),
            'role' => 'client',
        ]);
        $client2->profile()->create(['phone' => '+96311666666', 'address' => 'Client Two Residence', 'image' => 'worker_profiles/worker2.jpg']);

        $client3 = User::create([
            'fullname' => 'خضر كراويتة',
            'email' => 'client3@cleaning.com',
            'password' => Hash::make('password123'),
            'role' => 'client',
        ]);
        $client3->profile()->create(['phone' => '+96311777777', 'address' => 'Client Three Residence', 'image' => 'worker_profiles/worker3.jpg']);
    }
}
