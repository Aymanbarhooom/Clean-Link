<?php

namespace Tests\Feature;

use App\Http\Controllers\ComplaintController;
use App\Models\Company;
use App\Models\Complaint;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ComplaintControllerIndexTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        $this->app['db']->purge('sqlite');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('fullname')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->timestamps();
        });

        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
        });

        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en');
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en');
            $table->timestamps();
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedBigInteger('favoritable_id');
            $table->string('favoritable_type');
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manager_id')->constrained('users');
            $table->foreignId('region_id')->constrained('regions');
            $table->string('name_ar');
            $table->string('name_en');
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('category_id')->constrained('categories');
            $table->string('name_ar');
            $table->string('name_en');
            $table->timestamps();
        });

        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users');
            $table->string('title');
            $table->text('body');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->unsignedBigInteger('complaintable_id');
            $table->string('complaintable_type');
            $table->timestamps();
        });
    }

    public function test_client_receives_split_complaint_lists_without_pagination(): void
    {
        $client = User::create([
            'fullname' => 'Client User',
            'email' => 'client@example.com',
            'password' => bcrypt('password'),
            'role' => 'client',
        ]);

        $region = \Illuminate\Support\Facades\DB::table('regions')->insertGetId([
            'name_ar' => 'المنطقة',
            'name_en' => 'Region',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $category = \Illuminate\Support\Facades\DB::table('categories')->insertGetId([
            'name_ar' => 'الفئة',
            'name_en' => 'Category',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $company = Company::create([
            'manager_id' => $client->id,
            'region_id' => $region,
            'name_ar' => 'شركة',
            'name_en' => 'Company',
        ]);

        $service = Service::create([
            'company_id' => $company->id,
            'category_id' => $category,
            'name_ar' => 'خدمة',
            'name_en' => 'Service',
        ]);

        Complaint::create([
            'client_id' => $client->id,
            'title' => 'Company complaint',
            'body' => 'This is a company complaint body.',
            'is_read' => false,
            'complaintable_type' => Company::class,
            'complaintable_id' => $company->id,
        ]);

        Complaint::create([
            'client_id' => $client->id,
            'title' => 'Service complaint',
            'body' => 'This is a service complaint body.',
            'is_read' => false,
            'complaintable_type' => Service::class,
            'complaintable_id' => $service->id,
        ]);

        $this->actingAs($client);

        $response = (new ComplaintController())->index(new Request());
        $payload = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertArrayHasKey('companies_complains', $payload['data']);
        $this->assertArrayHasKey('services_complains', $payload['data']);
        $this->assertCount(1, $payload['data']['companies_complains']);
        $this->assertCount(1, $payload['data']['services_complains']);
        $this->assertArrayNotHasKey('pagination', $payload['data']);
    }
}
