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
        Schema::table('users', function (Blueprint $table) {
            // No connect_token counterpart (unlike telegram_chat_id) - Viber
            // has no self-service bot equivalent to BotFather, so there's no
            // real deep-link-and-webhook round trip to model yet. See
            // RateAlertController::connectViber() for what sets this today.
            $table->string('viber_chat_id')->nullable()->after('telegram_connect_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('viber_chat_id');
        });
    }
};
