<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('payment_method', ['card', 'cash'])
                  ->default('cash')
                  ->after('total_price');

            $table->enum('payment_status', ['pending', 'held', 'captured', 'refunded', 'failed'])
                  ->default('pending')
                  ->after('payment_method');

            $table->string('stripe_payment_intent_id')->nullable()->after('payment_status');

            $table->decimal('admin_share', 10, 2)->nullable()->after('stripe_payment_intent_id');
            $table->decimal('company_share', 10, 2)->nullable()->after('admin_share');

            $table->boolean('is_done_with_admin')->default(false)->after('company_share');

            $table->boolean('is_company_paid')->default(false)->after('is_done_with_admin');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method',
                'payment_status',
                'stripe_payment_intent_id',
                'admin_share',
                'company_share',
                'is_done_with_admin',
                'is_company_paid',
            ]);
        });
    }
};
