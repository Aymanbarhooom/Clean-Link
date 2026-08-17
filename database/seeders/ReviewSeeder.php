<?php
namespace Database\Seeders;

use App\Models\Review;
use App\Models\Company;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {

        $companies = Company::take(10)->get();
        $services = Service::take(10)->get();

        $templates = [
            ['client_id' => 4, 'rating' => 5, 'comment' => 'Excellent and highly professional service, will absolutely book again!'],
            ['client_id' => 5, 'rating' => 4, 'comment' => 'Very good service, workers are highly punctual and cleaning quality is great.'],
            ['client_id' => 6, 'rating' => 3, 'comment' => 'good experience and polite staff.']
        ];

        for ($i = 0; $i < 10; $i++) {
            $company = $companies->get($i);
            $service = $services->get($i);
            for ($j = 0; $j < 3; $j++) {
                $meta = $templates[$j];

                if ($company) {
                    Review::create([
                        'client_id'=> $meta['client_id'],
                        'comment' => $meta['comment'],
                        'rating' => $meta['rating'],
                        'reviewable_id' => $company->id,
                        'reviewable_type' => Company::class,
                    ]);
                }

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
