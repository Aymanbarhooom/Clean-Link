<?php
// database/seeders/ServiceImageSeeder.php
namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceImage;
use App\Models\Category;
use Illuminate\Database\Seeder;

class ServiceImageSeeder extends Seeder
{
    public function run(): void
    {
        $homeCleaning = Category::where('name_en', 'Home')->first();
        $carWash = Category::where('name_en', 'Car')->first();

        $services = Service::all();

        foreach ($services as $service) {
            
            if ($service->category_id === $homeCleaning?->id || $service->company?->category_id === $homeCleaning?->id) {
                
                ServiceImage::create([
                    'service_id' => $service->id,
                    'name' => 'Home Gallery Overview',
                    // Maps 3 specific home images located in your public workspace
                    'images' => [ 
                        'service_secondary/home_living.jpg',
                        'service_secondary/home_kitchen.jpg',
                        'service_secondary/home_bathroom.jpg'
                    ]
                ]);

            } elseif ($service->category_id === $carWash?->id || $service->company?->category_id === $carWash?->id) {
                
                ServiceImage::create([
                    'service_id' => $service->id,
                    'name' => 'Car Detailing Gallery Overview',
                    // Maps 3 alternative automotive images located in your public workspace
                    'images' => [
                        'service_secondary/car_interior.jpg',
                        'service_secondary/car_wheels.jpg',
                        'service_secondary/car_foam.jpg'
                    ]
                ]);
            }
        }
    }
}
