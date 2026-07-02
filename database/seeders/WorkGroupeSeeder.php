<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use App\Models\Workgroup;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkGroupeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Retrieve all company vendor profiles from database registry
        $companies = Company::all();

        foreach ($companies as $company) {
            
            $companyWorkers = $company->workers()->get();

            $workerChunks = $companyWorkers->chunk(2);
            $groupIndex = 1;

            foreach ($workerChunks as $pair) {
                if ($pair->count() < 2) continue; // Boundary safety check

                $workersArray = $pair->values();
                $leader = $workersArray[0]; // Per instructions: Leader is the first worker
                
                DB::transaction(function () use ($company, $groupIndex, $leader, $workersArray) {
                    // 1. Establish structural parent crew container
                    $workgroup = Workgroup::create([
                        'company_id' => $company->id,
                        'name' => 'Crew Team ' . $groupIndex . ' (' . $company->name_en . ')',
                        'leader_id' => $leader->id,
                    ]);

                    // 2. Map all workers within this specific pair chunk into the user_workgroup pivot table
                    $staffIds = [$workersArray[0]->id, $workersArray[1]->id];
                    $workgroup->workers()->sync($staffIds);
                });

                $groupIndex++;
            }
        }
    }
}
