<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Region;
use App\Models\Service;
use App\Models\Skill;
use App\Models\AttributeModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminSearchControllerTest extends TestCase
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
            $table->unsignedBigInteger('manager_id')->nullable();
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
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->string('name_en');
            $table->string('name_ar');
            $table->timestamps();
        });

        Schema::create('skills', function ($table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ar');
            $table->timestamps();
        });

        Schema::create('attributes', function ($table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ar');
            $table->string('type');
            $table->timestamps();
        });

        Schema::create('services', function ($table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('category_id');
            $table->string('name_en');
            $table->string('name_ar');
            $table->decimal('price', 8, 2)->default(0);
            $table->timestamps();
        });
    }

    public function test_admin_can_search_region_managers_by_fullname_and_email(): void
    {
        $admin = User::create([
            'fullname' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        User::create([
            'fullname' => 'Ali Hassan',
            'email' => 'ali@example.com',
            'password' => bcrypt('password'),
            'role' => 'region_manager',
        ]);

        User::create([
            'fullname' => 'Sara Ahmed',
            'email' => 'sara@example.com',
            'password' => bcrypt('password'),
            'role' => 'region_manager',
        ]);

        $this->actingAs($admin, 'sanctum');

        $response = $this->getJson('/api/admin/search/region-managers?query=ali');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertSame('Ali Hassan', $response->json('data.0.fullname'));
    }

    public function test_region_manager_can_search_companies_only_from_their_region(): void
    {
        $regionManager = User::create([
            'fullname' => 'Region Manager',
            'email' => 'rm@example.com',
            'password' => bcrypt('password'),
            'role' => 'region_manager',
        ]);

        $regionOne = Region::create([
            'name_en' => 'Amman',
            'name_ar' => 'عمان',
            'manager_id' => $regionManager->id,
        ]);

        $regionTwo = Region::create([
            'name_en' => 'Zarqa',
            'name_ar' => 'الزرقاء',
            'manager_id' => $regionManager->id,
        ]);

        Company::create([
            'region_id' => $regionOne->id,
            'manager_id' => $regionManager->id,
            'name_en' => 'Clean House',
            'name_ar' => 'بيت نظيف',
        ]);

        Company::create([
            'region_id' => $regionTwo->id,
            'manager_id' => $regionManager->id,
            'name_en' => 'Bright Office',
            'name_ar' => 'مكتب مشرق',
        ]);

        $this->actingAs($regionManager, 'sanctum');

        $response = $this->getJson('/api/admin/search/companies?query=clean');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Clean House', $response->json('data.0.name_en'));
    }
}
