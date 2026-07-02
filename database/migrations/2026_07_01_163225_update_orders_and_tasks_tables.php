<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'time')) {
                $table->renameColumn('time', 'start_time');
            }
            
            $table->dateTime('end_time')->after('start_time');
            $table->integer('duration')->comment('Package block duration in minutes')->after('end_time');
        });

        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'worker_id')) {
                $table->dropForeign(['worker_id']);
                $table->dropColumn('worker_id');
            }

            $table->foreignId('workgroup_id')->after('order_id')->constrained('workgroups')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['workgroup_id']);
            $table->dropColumn('workgroup_id');
            $table->foreignId('worker_id')->constrained('users')->onDelete('cascade');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['end_time', 'duration']);
            $table->renameColumn('start_time', 'time');
        });
    }
};
