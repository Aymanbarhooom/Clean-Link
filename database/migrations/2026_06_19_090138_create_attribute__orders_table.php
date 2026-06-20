<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_order', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('attribute_id')->constrained('attributes')->onDelete('cascade');
            $table->integer('qty')->default(1);
            $table->decimal('price_at_order', 10, 2)->comment('Locked-in historic price point');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_order');
    }
};
