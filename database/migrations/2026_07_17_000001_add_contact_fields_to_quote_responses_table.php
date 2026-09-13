<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_responses', function (Blueprint $table) {
            $table->string('contact_phone')->nullable()->after('reply_text');
            $table->string('contact_whatsapp')->nullable()->after('contact_phone');
            $table->string('contact_telegram')->nullable()->after('contact_whatsapp');
            $table->string('contact_instagram')->nullable()->after('contact_telegram');
        });
    }

    public function down(): void
    {
        Schema::table('quote_responses', function (Blueprint $table) {
            $table->dropColumn(['contact_phone', 'contact_whatsapp', 'contact_telegram', 'contact_instagram']);
        });
    }
};
