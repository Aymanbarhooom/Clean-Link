<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('packages', 'is_open_package')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->boolean('is_open_package')
                    ->default(false)
                    ->after('minimum_workers');
            });
        }

        DB::table('packages')
            ->whereRaw('LOWER(TRIM(name_en)) = ?', ['open package'])
            ->update(['is_open_package' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('packages', 'is_open_package')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->dropColumn('is_open_package');
            });
        }
    }
};
