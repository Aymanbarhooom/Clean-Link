<?php

// database/seeders/PackageSeeder.php
namespace Database\Seeders;

use App\Models\Service;
use App\Models\Package;
use App\Models\Category;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $homeCleaning = Category::where('name_en', 'Home Cleaning')->orWhere('name_en', 'Home')->first();
        $carWash = Category::where('name_en', 'Car Detailing & Wash')->orWhere('name_en', 'Car')->first();

        // Retrieve all 10 pre-seeded corporate services
        $services = Service::all();

        foreach ($services as $service) {
            
            // Check if the parent company or service links to the Residential Cleaning Vertical
            if ($service->category_id === $homeCleaning?->id || $service->company?->category_id === $homeCleaning?->id) {
                
                // Package 1: Studio Layout
                Package::create([
                    'service_id' => $service->id,
                    'name' => 'Studio Package',
                    'duration' => max(45, intval($service->min_duration * 0.6)),
                    'price' => 80,
                    'price_after_discount' => $service->discount > 0 ? 80 - (80 * ($service->discount/100)) : 80,
                    'details' => [
                        'Ideal for spaces under 60m²',
                        'Includes 1 main living area & 1 bathroom refresh',
                        'Basic surface dusting & floor disinfection'
                    ],
                ]);

                // Package 2: Standard Flat Layout
                Package::create([
                    'service_id' => $service->id,
                    'name' => 'Standard Flat Package',
                    'duration' => intval(($service->min_duration + $service->max_duration) / 2),
                    'price' => 60,
                    'price_after_discount' => $service->discount > 0 ? 60 - (60 * ($service->discount/100)) : 60,
                    'details' => [
                        'Covers typical family flats up to 140m²',
                        'Deep wash of 3 rooms & 2 dedicated bathrooms',
                        'Kitchen outer cabinets grease wipe'
                    ],
                ]);

                // Package 3: Premium Multi-Story Building
                Package::create([
                    'service_id' => $service->id,
                    'name' => 'Duplex & Building Package',
                    'duration' => intval($service->max_duration * 1.5),
                    'price' => 108,
                    'price_after_discount' => $service->discount > 0 ? 108 - (108 * ($service->discount/100)) : 108,
                    'details' => [
                        'Suited for large duplex configurations or multi-story builds',
                        'Includes 5+ rooms, staircase sweeping, and balconies',
                        'Complete roof boundary clearing & external window wash tint'
                    ],
                ]);

            } 
            // Otherwise apply the automotive caravan tracking configuration details layout
            elseif ($service->category_id === $carWash?->id || $service->company?->category_id === $carWash?->id) {
                
                // Package 1: Small Sedan Frame
                Package::create([
                    'service_id' => $service->id,
                    'name' => 'Sedan / Hatchback Tier',
                    'duration' => max(20, intval($service->min_duration * 0.8)),
                    'price' => 24,
                    'price_after_discount' => $service->discount > 0 ? (24 - (24 * ($service->discount/100))) : 24,
                    'details' => [
                        'Optimized for compact passenger vehicles & daily sedans',
                        'Standard 4-seat matching cabin vacuuming',
                        'Dashboard wipe down & tire gloss polish spray'
                    ],
                ]);

                // Package 2: Standard SUV Setup
                Package::create([
                    'service_id' => $service->id,
                    'name' => 'SUV & Crossover Tier',
                    'duration' => intval(($service->min_duration + $service->max_duration) / 2),
                    'price' => 60,
                    'price_after_discount' => $service->discount > 0 ?60 - (60 * ($service->discount/100)) : 60,
                    'details' => [
                        'Tailored specifically for 5 to 7 passenger crossover vehicles',
                        'Trunk storage debris extraction included',
                        'Mat washing & deep AC vent odor neutralizer application'
                    ],
                ]);

                // Package 3: Commercial Van or Luxury Elite Truck
                Package::create([
                    'service_id' => $service->id,
                    'name' => 'Large Van & Truck Elite Tier',
                    'duration' => intval($service->max_duration * 1.3),
                    'price' => 240,
                    'price_after_discount' => $service->discount > 0 ?240 - (240 * ($service->discount/100)) : 240,
                    'details' => [
                        'Engineered for large pickup trucks, luxury 4x4s, and multi-row vans',
                        'Full leather treatment conditioning or heavy fabric steam scrubbing',
                        'Complete undercarriage mud jet blasting'
                    ],
                ]);
            }
        }
    }
}
