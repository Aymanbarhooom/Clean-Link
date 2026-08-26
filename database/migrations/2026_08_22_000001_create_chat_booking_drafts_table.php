<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_booking_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_conversation_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('start_time')->nullable();
            $table->enum('payment_method', ['cash', 'card'])->nullable();
            $table->text('note')->nullable();
            $table->boolean('note_handled')->default(false);
            $table->json('open_package_attributes')->nullable();
            $table->decimal('quoted_price', 10, 2)->nullable();
            $table->unsignedInteger('quoted_duration')->nullable();
            $table->string('summary_hash', 64)->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('created_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'created_order_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('chat_booking_drafts'); }
};
