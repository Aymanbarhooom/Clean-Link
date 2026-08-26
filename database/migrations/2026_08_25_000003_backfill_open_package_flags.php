<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('packages', 'is_open_package')) {
            return;
        }

        DB::table('packages')
            ->where(function ($query) {
                $query
                    ->whereRaw('LOWER(TRIM(name_en)) IN (?, ?, ?)', [
                        'open package',
                        'openpackage',
                        'open-package',
                    ])
                    ->orWhereIn('name_ar', [
                        'الباقة المفتوحة',
                        'باقة مفتوحة',
                        'باقة مفتوحه',
                    ]);
            })
            ->update(['is_open_package' => true]);
    }

    public function down(): void
    {
        // This is a data correction. Reverting it would make valid Open Packages unusable.
    }
};
