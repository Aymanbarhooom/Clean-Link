<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\Package;
use App\Models\Service;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CompanyManagerDashboardApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        $this->app['db']->purge('sqlite');
        $this->createSchema();
    }

    public function test_order_locations_require_authentication(): void
    {
        $this->getJson('/api/orders/locations')->assertUnauthorized();
    }

    public function test_company_manager_receives_only_own_orders_with_coordinates(): void
    {
        $manager = $this->createUser('company_manager', 'manager@example.com');
        $otherManager = $this->createUser('company_manager', 'other-manager@example.com');
        $client = $this->createUser('client', 'client@example.com');
        $ownCompany = $this->createCompany($manager, 'Own Company');
        $otherCompany = $this->createCompany($otherManager, 'Other Company');
        $ownPackage = $this->createPackage($this->createService($ownCompany, 'Own Service'));
        $otherPackage = $this->createPackage($this->createService($otherCompany, 'Other Service'));

        $visible = $this->createOrder($client, $ownPackage, 33.5138, 36.2765);
        $this->createOrder($client, $ownPackage, null, null);
        $this->createOrder($client, $otherPackage, 32.0, 35.0);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/orders/locations', ['Accept-Language' => 'en']);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id)
            ->assertJsonPath('data.0.latitude', 33.5138)
            ->assertJsonPath('data.0.longitude', 36.2765)
            ->assertJsonPath('data.0.client.fullname', 'client')
            ->assertJsonPath('data.0.package.name', 'Package')
            ->assertJsonPath('data.0.service.name', 'Own Service')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [[
                    'id', 'latitude', 'longitude', 'status', 'location',
                    'start_time', 'end_time', 'total_price',
                    'client' => ['id', 'fullname'],
                    'package' => ['id', 'name'],
                    'service' => ['id', 'name'],
                ]],
            ]);
    }

    public function test_worker_search_preserves_search_and_paginates_at_the_database(): void
    {
        $manager = $this->createUser('company_manager', 'manager@example.com');
        $otherManager = $this->createUser('company_manager', 'other-manager@example.com');
        $company = $this->createCompany($manager, 'Own Company');
        $otherCompany = $this->createCompany($otherManager, 'Other Company');

        for ($index = 1; $index <= 12; $index++) {
            $worker = $this->createUser('worker', "worker{$index}@example.com", "Search Worker {$index}");
            $worker->workerProfile()->create(['company_id' => $company->id]);
        }

        $otherWorker = $this->createUser('worker', 'other@example.com', 'Search Worker Other');
        $otherWorker->workerProfile()->create(['company_id' => $otherCompany->id]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/workers/search?query=Search%20Worker&page=2&per_page=10');

        $response->assertOk()
            ->assertJsonCount(2, 'data.data')
            ->assertJsonPath('data.pagination.current_page', 2)
            ->assertJsonPath('data.pagination.per_page', 10)
            ->assertJsonPath('data.pagination.total', 12)
            ->assertJsonPath('data.pagination.last_page', 2)
            ->assertJsonPath('data.pagination.from', 11)
            ->assertJsonPath('data.pagination.to', 12)
            ->assertJsonPath('data.pagination.has_more_pages', false);
    }

    public function test_service_skills_return_only_assigned_skills_with_pagination(): void
    {
        $manager = $this->createUser('company_manager', 'manager@example.com');
        $company = $this->createCompany($manager, 'Own Company');
        $service = $this->createService($company, 'Cleaning');

        for ($index = 1; $index <= 13; $index++) {
            $skill = Skill::create([
                'name_ar' => "مهارة {$index}",
                'name_en' => "Skill {$index}",
            ]);

            if ($index <= 12) {
                $service->requiredSkills()->attach($skill->id);
            }
        }

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson("/api/services/{$service->id}/skills?page=2&per_page=10", [
                'Accept-Language' => 'en',
            ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data.data')
            ->assertJsonPath('data.pagination.current_page', 2)
            ->assertJsonPath('data.pagination.per_page', 10)
            ->assertJsonPath('data.pagination.total', 12)
            ->assertJsonPath('data.pagination.last_page', 2)
            ->assertJsonPath('data.pagination.has_more_pages', false)
            ->assertJsonStructure([
                'data' => [
                    'data' => [['id', 'name']],
                    'pagination' => [
                        'current_page', 'per_page', 'total', 'last_page',
                        'from', 'to', 'has_more_pages',
                    ],
                ],
            ]);
    }

    public function test_company_manager_cannot_retrieve_another_company_service_skills(): void
    {
        $manager = $this->createUser('company_manager', 'manager@example.com');
        $otherManager = $this->createUser('company_manager', 'other-manager@example.com');
        $this->createCompany($manager, 'Own Company');
        $otherService = $this->createService(
            $this->createCompany($otherManager, 'Other Company'),
            'Other Service'
        );

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/services/{$otherService->id}/skills")
            ->assertForbidden();
    }

    public function test_existing_orders_services_and_workers_endpoints_accept_ten_per_page(): void
    {
        $manager = $this->createUser('company_manager', 'manager@example.com');
        $client = $this->createUser('client', 'client@example.com');
        $company = $this->createCompany($manager, 'Own Company');
        $service = $this->createService($company, 'Cleaning');
        $package = $this->createPackage($service);

        for ($index = 1; $index <= 11; $index++) {
            $this->createOrder($client, $package, 33.0 + ($index / 100), 36.0);

            $worker = $this->createUser('worker', "worker{$index}@example.com", "Worker {$index}");
            $worker->workerProfile()->create(['company_id' => $company->id]);
        }

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/orders?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data.data')
            ->assertJsonPath('data.pagination.per_page', 10)
            ->assertJsonPath('data.pagination.total', 11);

        $this->getJson("/api/services?company_id={$company->id}&per_page=10")
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.pagination.per_page', 10)
            ->assertJsonPath('data.pagination.total', 1);

        $this->getJson("/api/workers/{$company->id}?per_page=10")
            ->assertOk()
            ->assertJsonCount(10, 'data.data')
            ->assertJsonPath('data.pagination.per_page', 10)
            ->assertJsonPath('data.pagination.total', 11);
    }

    private function createUser(string $role, string $email, ?string $fullname = null): User
    {
        return User::create([
            'fullname' => $fullname ?? strstr($email, '@', true),
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => $role,
        ]);
    }

    private function createCompany(User $manager, string $name): Company
    {
        return Company::create([
            'manager_id' => $manager->id,
            'region_id' => 1,
            'name_ar' => $name,
            'name_en' => $name,
        ]);
    }

    private function createService(Company $company, string $name): Service
    {
        return Service::create([
            'company_id' => $company->id,
            'category_id' => 1,
            'name_ar' => $name,
            'name_en' => $name,
        ]);
    }

    private function createPackage(Service $service): Package
    {
        return Package::create([
            'service_id' => $service->id,
            'name_ar' => 'Package',
            'name_en' => 'Package',
            'duration' => 60,
            'price' => 100,
            'price_after_discount' => 100,
            'minimum_workers' => 1,
        ]);
    }

    private function createOrder(
        User $client,
        Package $package,
        ?float $latitude,
        ?float $longitude
    ): Order {
        return Order::create([
            'client_id' => $client->id,
            'package_id' => $package->id,
            'location' => 'Map location',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => 'pending',
            'total_price' => 150,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('fullname');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('manager_id');
            $table->unsignedBigInteger('region_id');
            $table->string('name_ar');
            $table->string('name_en');
            $table->timestamps();
        });

        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar')->nullable();
            $table->string('name_en')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar')->nullable();
            $table->string('name_en')->nullable();
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('category_id');
            $table->string('name_ar');
            $table->string('name_en');
            $table->timestamps();
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_id');
            $table->string('name_ar');
            $table->string('name_en');
            $table->integer('duration');
            $table->decimal('price', 10, 2);
            $table->decimal('price_after_discount', 10, 2)->nullable();
            $table->integer('minimum_workers')->default(1);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('package_id');
            $table->string('location');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            $table->string('status');
            $table->decimal('total_price', 10, 2);
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('workgroup_id')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('worker_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('company_id');
            $table->integer('experience_years')->default(0);
            $table->decimal('rating', 3, 2)->default(0);
            $table->string('status')->default('available');
            $table->timestamps();
        });

        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('image')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::create('workgroups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name');
            $table->unsignedBigInteger('leader_id')->nullable();
            $table->timestamps();
        });

        Schema::create('user_workgroup', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('workgroup_id');
            $table->timestamps();
        });

        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar')->unique();
            $table->string('name_en')->unique();
            $table->timestamps();
        });

        Schema::create('service_skills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('skill_id');
            $table->timestamps();
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('favoritable_id');
            $table->string('favoritable_type');
            $table->timestamps();
        });

        \Illuminate\Support\Facades\DB::table('regions')->insert([
            'id' => 1,
            'name_ar' => 'Region',
            'name_en' => 'Region',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \Illuminate\Support\Facades\DB::table('categories')->insert([
            'id' => 1,
            'name_ar' => 'Category',
            'name_en' => 'Category',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
