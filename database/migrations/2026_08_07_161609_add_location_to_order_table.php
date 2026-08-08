<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->after('location');
            $table->decimal('longitude', 10, 7)->after('latitude');
            $table->unsignedInteger('travel_buffer_minutes')->default(0)->after('duration');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'travel_buffer_minutes']);
        });
    }
};