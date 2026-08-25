<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_booking_drafts', function (Blueprint $table) {
            $table->unsignedInteger('summary_message_count')->nullable()->after('summary_hash');
        });
    }

    public function down(): void
    {
        Schema::table('chat_booking_drafts', fn (Blueprint $table) => $table->dropColumn('summary_message_count'));
    }
};
