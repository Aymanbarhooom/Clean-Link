<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->onDelete('cascade');
            $table->text('comment')->nullable();
            $table->tinyInteger('rating')->unsigned();
            
            // Polymorphic Columns for Service & Company models
            $table->unsignedBigInteger('reviewable_id');
            $table->string('reviewable_type');
            $table->index(['reviewable_id', 'reviewable_type']);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
