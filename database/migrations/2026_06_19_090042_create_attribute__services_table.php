<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->foreignId('attribute_id')->constrained('attributes')->onDelete('cascade');
            $table->decimal('price', 10, 2)->default(0.00);
            $table->integer('duration')->default(0)->comment('extra duration in minutes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_service');
    }
};
