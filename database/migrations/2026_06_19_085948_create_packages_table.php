<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->string('name_ar');
            $table->string('name_en');
            $table->integer('duration')->comment('in minutes');
            $table->decimal('price', 10, 2);
            $table->decimal('price_after_discount',10, 2)->nullable()->comment('calculated price after applying any service-level discount');
            $table->json('details_ar'); // Holds an array/list of strings
            $table->json('details_en'); // Holds an array/list of strings
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
