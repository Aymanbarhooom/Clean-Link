<?php
// database/seeders/ReviewSeeder.php
namespace Database\Seeders;

use App\Models\Review;
use App\Models\Company;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {

        // Fetch your 10 corporate entities and 10 services
        // Using take(10) to match your exact loop constraint safety margin
        $companies = Company::take(10)->get();
        $services = Service::take(10)->get();

        // Standardized custom templates list to cycle variations over iterations
        $templates = [
            ['client_id' => 6, 'rating' => 5, 'comment' => 'Excellent and highly professional service, will absolutely book again!'],
            ['client_id' => 7, 'rating' => 4, 'comment' => 'Very good service, workers are highly punctual and cleaning quality is great.'],
            ['client_id' => 8, 'rating' => 3, 'comment' => 'good experience and polite staff.']
        ];

        // Run the main sequential loop across 10 elements
        for ($i = 0; $i < 10; $i++) {
            $company = $companies->get($i);
            $service = $services->get($i);
            for ($j = 0; $j < 3; $j++) {
                $meta = $templates[$j];

                // 1. Inject polymorphic review mapping to the targeted Company entity
                if ($company) {
                    Review::create([
                        'client_id'=> $meta['client_id'],
                        'comment' => $meta['comment'], // Will map localized headers at API level dynamically
                        'rating' => $meta['rating'],
                        'reviewable_id' => $company->id,
                        'reviewable_type' => Company::class,
                    ]);
                }

                // 2. Inject polymorphic review mapping to the targeted Service entity
                if ($service) {
                    Review::create([
                        'client_id'=> $meta['client_id'],
                        'comment' => $meta['comment'],
                        'rating' => $meta['rating'],
                        'reviewable_id' => $service->id,
                        'reviewable_type' => Service::class,
                    ]);
                }
            }

        }
    }
}
