<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accepting an offer, without telling the exchange office who accepted it.
     *
     * The request gets a short public code (FX-48372) and each response gets
     * that code plus a letter (FX-48372-A). The letter is what a visitor reads
     * out at the counter, so it has to be short, unambiguous and carry no
     * personal information at all - the office looks it up against the request
     * it already answered.
     */
    public function up(): void
    {
        Schema::table('exchange_quote_requests', function (Blueprint $table) {
            $table->string('public_code', 16)->nullable()->unique()->after('id');
        });

        Schema::table('exchange_quote_responses', function (Blueprint $table) {
            // The letter this response was given within its request. Stored
            // rather than derived from row order, so a code a visitor wrote
            // down cannot change meaning if rows are ever re-ordered.
            $table->string('offer_letter', 2)->nullable()->after('status');
            $table->timestamp('accepted_at')->nullable()->after('responded_at');
        });
    }

    public function down(): void
    {
        Schema::table('exchange_quote_requests', function (Blueprint $table) {
            $table->dropColumn('public_code');
        });

        Schema::table('exchange_quote_responses', function (Blueprint $table) {
            $table->dropColumn(['offer_letter', 'accepted_at']);
        });
    }
};
