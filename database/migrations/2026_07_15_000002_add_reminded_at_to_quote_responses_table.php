<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks whether a still-pending QuoteResponse has already had its one
 * nudge reminder sent (see RemindPartnersOfPendingQuotes) - without this,
 * the reminder command would re-notify the same partner every time it runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_responses', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable()->after('responded_at');
        });
    }

    public function down(): void
    {
        Schema::table('quote_responses', function (Blueprint $table) {
            $table->dropColumn('reminded_at');
        });
    }
};
