<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('worker_profiles', function (Blueprint $blueprint) {
            $blueprint->enum('status', ['available', 'busy', 'off'])->default('available');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('worker_profiles', function (Blueprint $blueprint) {
            $blueprint->dropColumn('status');
        });
    }
};
    