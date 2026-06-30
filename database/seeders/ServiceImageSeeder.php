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
                    'image_before' => 'service_secondary/home_bathroom.jpg',
                    'image_after' => 'service_secondary/home_kitchen.jpg',
                ]);
                ServiceImage::create([
                    'service_id' => $service->id,
                    'image_before' => 'service_secondary/home_living.jpg',
                    'image_after' => 'service_secondary/home_bathroom.jpg',
                ]);

            } elseif ($service->category_id === $carWash?->id || $service->company?->category_id === $carWash?->id) {
                
                ServiceImage::create([
                    'service_id' => $service->id,
                    'image_before' => 'service_secondary/car_foam.jpg',
                    'image_after' => 'service_secondary/car_interior.jpg',
                ]);
                ServiceImage::create([
                    'service_id' => $service->id,
                    'image_before' => 'service_secondary/car_wheels.jpg',
                    'image_after' => 'service_secondary/car_foam.jpg',
                ]);
            }
        }
    }
}
