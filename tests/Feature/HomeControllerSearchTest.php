<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Region;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HomeControllerSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->app['db']->purge('sqlite');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('fullname');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('client');
            $table->timestamps();
        });

        Schema::create('regions', function ($table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ar');
            $table->timestamps();
        });

        Schema::create('categories', function ($table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ar');
            $table->timestamps();
        });

        Schema::create('companies', function ($table) {
            $table->id();
            $table->unsignedBigInteger('region_id');
            $table->string('name_en');
            $table->string('name_ar');
            $table->decimal('rating', 3, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('services', function ($table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name_en');
            $table->string('name_ar');
            $table->decimal('rating', 3, 2)->default(0);
            $table->decimal('price', 8, 2)->default(0);
            $table->decimal('discount', 8, 2)->default(0);
            $table->timestamps();
        });
    }

    public function test_search_filters_services_and_offers_by_region_price_and_rating(): void
    {
        $region = Region::create([
            'name_en' => 'Amman',
            'name_ar' => 'عمان',
        ]);

        $company = Company::create([
            'region_id' => $region->id,
            'name_en' => 'Clean House',
            'name_ar' => 'بيت نظيف',
            'rating' => 4.2,
        ]);

        Service::create([
            'company_id' => $company->id,
            'name_en' => 'Clean Sofa',
            'name_ar' => 'تنظيف أريكة',
            'rating' => 3.2,
            'price' => 120,
            'discount' => 0,
        ]);

        Service::create([
            'company_id' => $company->id,
            'name_en' => 'Deep Clean',
            'name_ar' => 'تنظيف عميق',
            'rating' => 3.8,
            'price' => 80,
            'discount' => 15,
        ]);

        Service::create([
            'company_id' => $company->id,
            'name_en' => 'Window Clean',
            'name_ar' => 'تنظيف شبابيك',
            'rating' => 4.4,
            'price' => 20,
            'discount' => 0,
        ]);

        $this->withoutMiddleware();

        $response = $this->getJson('/api/search?query=clean&region_id=' . $region->id . '&price_range=10-100&rating=3.5');

        $response->assertOk();

        $services = collect($response->json('data.services'));
        $offers = collect($response->json('data.offers'));

        $this->assertSame([2], $services->pluck('id')->all());
        $this->assertSame([2], $offers->pluck('id')->all());
    }
}
