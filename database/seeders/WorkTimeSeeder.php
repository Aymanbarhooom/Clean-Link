<?php


namespace Database\Seeders;

use App\Models\Company;
use App\Models\WorkTime;
use Illuminate\Database\Seeder;

class WorkTimeSeeder extends Seeder
{
    public function run(): void
    {
        $companies = Company::all();

        foreach ($companies as $company) {
            for ($day = 0; $day <= 6; $day++) {
                
                $isHoliday = ($day == 5 || $day == 6);

                WorkTime::create([
                    'company_id'  => $company->id,
                    'day_of_week' => $day,
                    'open_at'     => $isHoliday ? null : '08:00:00',
                    'close_at'    => $isHoliday ? null : '16:00:00',
                    'is_holiday'  => $isHoliday,
                ]);
            }
        }
    }
}
