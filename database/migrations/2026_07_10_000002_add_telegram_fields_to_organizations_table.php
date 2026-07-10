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
        Schema::table('organizations', function (Blueprint $table) {
            // Lets Findex message this organization on Telegram (tourism
            // quote requests today) - null until they complete the one-time
            // "connect Telegram" deep link, since a bot can't message a chat
            // that has never messaged it first.
            $table->string('telegram_chat_id')->nullable()->after('logo');
            $table->string('telegram_connect_token')->nullable()->unique()->after('telegram_chat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['telegram_chat_id', 'telegram_connect_token']);
        });
    }
};
