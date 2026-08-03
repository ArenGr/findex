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
            // Same connect-token pattern as organizations (see
            // 2026_07_10_000002_add_telegram_fields_to_organizations_table) -
            // null until the user completes the one-time "connect Telegram"
            // deep link, since a bot can't message a chat that has never
            // messaged it first.
            $table->string('telegram_chat_id')->nullable()->after('avatar');
            $table->string('telegram_connect_token')->nullable()->unique()->after('telegram_chat_id');
            // Snapshotted when the connect link is generated (from the
            // locale the user was browsing in at the time) so the bot can
            // reply in the right language - there's no other locale signal
            // available inside a Telegram webhook call.
            $table->string('locale', 5)->nullable()->after('telegram_connect_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telegram_chat_id', 'telegram_connect_token', 'locale']);
        });
    }
};
