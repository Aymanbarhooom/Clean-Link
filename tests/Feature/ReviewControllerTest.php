<?php

namespace Tests\Feature;

use App\Http\Controllers\ReviewController;
use App\Models\Company;
use App\Models\Order;
use App\Models\Package;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReviewControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');
        DB::reconnect();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('fullname');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('client');
            $table->timestamps();
        });

        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manager_id')->nullable();
            $table->foreignId('region_id')->nullable();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('description_ar')->nullable();
            $table->string('description_en')->nullable();
            $table->string('image')->nullable();
            $table->string('location_ar')->nullable();
            $table->string('location_en')->nullable();
            $table->float('rating')->default(0);
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('description_ar')->nullable();
            $table->string('description_en')->nullable();
            $table->float('rating')->default(0);
            $table->timestamps();
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en');
            $table->integer('duration')->default(60);
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('price_after_discount', 10, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->string('location');
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            $table->integer('duration')->nullable();
            $table->string('status')->default('pending');
            $table->decimal('total_price', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->text('comment')->nullable();
            $table->integer('rating');
            $table->unsignedBigInteger('reviewable_id');
            $table->string('reviewable_type');
            $table->timestamps();
        });
    }

    public function test_client_without_used_order_cannot_review(): void
    {
        $client = User::create([
            'fullname' => 'Test Client',
            'email' => 'client@example.com',
            'password' => bcrypt('password'),
            'role' => 'client',
        ]);

        $company = Company::create([
            'manager_id' => 1,
            'region_id' => 1,
            'name_ar' => 'الشركة',
            'name_en' => 'Company',
            'description_ar' => 'desc',
            'description_en' => 'desc',
            'image' => null,
            'location_ar' => 'loc',
            'location_en' => 'loc',
            'rating' => 0,
        ]);

        $service = Service::create([
            'company_id' => $company->id,
            'category_id' => 1,
            'name_ar' => 'الخدمة',
            'name_en' => 'Service',
            'description_ar' => 'desc',
            'description_en' => 'desc',
            'rating' => 0,
        ]);

        $package = Package::create([
            'service_id' => $service->id,
            'name_ar' => 'الباقة',
            'name_en' => 'Package',
            'duration' => 60,
            'price' => 100,
            'price_after_discount' => 100,
        ]);

        Order::create([
            'client_id' => $client->id,
            'package_id' => $package->id,
            'location' => 'Cairo',
            'start_time' => now()->subDay(),
            'end_time' => now()->subDay()->addHour(),
            'duration' => 60,
            'status' => 'pending',
            'total_price' => 100,
        ]);

        $this->actingAs($client);

        $controller = new ReviewController();
        $request = new Request([
            'type' => 'service',
            'id' => $service->id,
            'comment' => 'Nice service',
            'rating' => 5,
        ]);

        $response = $controller->store($request);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertSame('Only clients who used this can review!', $response->getData(true)['message']);
    }
}
