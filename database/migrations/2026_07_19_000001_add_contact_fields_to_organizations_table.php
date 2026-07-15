<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generalized across every org type (not tourism-specific like
     * quote_responses' own contact fields) - a bank or insurer's public
     * profile can show these too, and auto insurance quotes (generated
     * automatically, with no per-request human response step to attach
     * contact info to) read from here directly.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('contact_phone')->nullable()->after('website');
            $table->string('contact_whatsapp')->nullable()->after('contact_phone');
            $table->string('contact_telegram')->nullable()->after('contact_whatsapp');
            $table->string('contact_instagram')->nullable()->after('contact_telegram');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['contact_phone', 'contact_whatsapp', 'contact_telegram', 'contact_instagram']);
        });
    }
};
