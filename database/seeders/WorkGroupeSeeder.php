<?php

namespace Database\Seeders;

use App\Models\Workgroup;
use App\Models\WorkerProfile;
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
        $workers = WorkerProfile::with(['user', 'company'])
            ->orderBy('company_id')
            ->get();

        if ($workers->isEmpty()) {
            $this->command->info('No workers found in the system.');
            return;
        }

        $this->command->info('All workers in system:');
        foreach ($workers as $index => $worker) {
            $name = optional($worker->user)->fullname ?: 'Unknown';
            $companyName = optional($worker->company)->name_en ?: 'No Company';
            $this->command->info(sprintf(
                '  %d) %s (user_id=%d, company_id=%s, company=%s)',
                $index + 1,
                $name,
                $worker->user_id,
                $worker->company_id ?? 'null',
                $companyName
            ));
        }

        $workersByCompany = $workers->groupBy('company_id');

        foreach ($workersByCompany as $companyId => $companyWorkers) {
            $company = $companyWorkers->first()->company;
            $companyName = optional($company)->name_en ?: 'Unknown Company';

            $this->command->info("\nGrouping workers for company: {$companyName} (ID: {$companyId})");

            $pairChunks = $companyWorkers->chunk(2);
            $groupIndex = 1;

            foreach ($pairChunks as $pair) {
                if ($pair->count() < 2) {
                    $worker = $pair->first();
                    $name = optional($worker->user)->fullname ?: 'Unknown';
                    $this->command->warn("Skipping lone worker {$name} (user_id={$worker->user_id}) because only one worker remains.");
                    continue;
                }

                $workersArray = $pair->values();
                $leader = $workersArray[0];

                DB::transaction(function () use ($company, $groupIndex, $leader, $workersArray) {
                    $workgroup = Workgroup::create([
                        'company_id' => $company->id,
                        'name' => 'Crew Team ' . $groupIndex . ' (' . optional($company)->name_en . ')',
                        'leader_id' => $leader->user_id,
                    ]);

                    $staffIds = $workersArray->pluck('user_id')->toArray();
                    $workgroup->workers()->sync($staffIds);
                });

                $memberNames = $workersArray->map(fn ($profile) => optional($profile->user)->fullname ?: 'Unknown')->implode(', ');
                $this->command->info("  Created workgroup {$groupIndex} with leader {$leader->user->fullname} and members: {$memberNames}");

                $groupIndex++;
            }
        }

        $this->command->info('Workgroup seeding completed.');
    }
}
