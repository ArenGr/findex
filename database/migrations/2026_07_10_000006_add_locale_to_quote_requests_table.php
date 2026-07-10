<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The visitor's site locale at submission time, so later async emails
     * (e.g. a partner's reply arriving via the Telegram webhook, with no
     * HTTP request/SetLocale middleware in play) can still be written in
     * the language the guest actually browsed the site in.
     */
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->string('locale', 5)->default(config('localization.default'))->after('guest_email');
        });
    }

    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
