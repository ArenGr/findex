<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Run the migrations.
    public function up(): void
    {
        Schema::table('rate_alerts', function (Blueprint $table) {
            $table->string('viber_chat_id')->nullable()->after('telegram_chat_id');
        });
    }

    // Reverse the migrations.
    public function down(): void
    {
        Schema::table('rate_alerts', function (Blueprint $table) {
            $table->dropColumn('viber_chat_id');
        });
    }
};
