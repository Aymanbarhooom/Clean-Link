<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'orders' => ['nullable' => false, 'default' => 'cash'],
            'payments' => ['nullable' => false, 'default' => null],
            'chat_booking_drafts' => ['nullable' => true, 'default' => null],
        ];

        foreach ($columns as $table => $options) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'payment_method')) {
                continue;
            }

            if (DB::getDriverName() === 'mysql') {
                $nullSql = $options['nullable'] ? 'NULL' : 'NOT NULL';
                $defaultSql = $options['default'] === null
                    ? ''
                    : " DEFAULT '".$options['default']."'";
                DB::statement("ALTER TABLE `{$table}` MODIFY `payment_method` VARCHAR(20) {$nullSql}{$defaultSql}");
            }

            DB::table($table)->where('payment_method', 'manual')->update(['payment_method' => 'cash']);
            DB::table($table)->whereIn('payment_method', ['electric', 'electronic'])->update(['payment_method' => 'card']);

            if (DB::getDriverName() === 'mysql') {
                $nullSql = $options['nullable'] ? 'NULL' : 'NOT NULL';
                $defaultSql = $options['default'] === null
                    ? ''
                    : " DEFAULT '".$options['default']."'";
                DB::statement("ALTER TABLE `{$table}` MODIFY `payment_method` ENUM('cash','card') {$nullSql}{$defaultSql}");
            }
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: restoring legacy names would corrupt the
        // canonical payment contract and could break newly-created records.
    }
};
