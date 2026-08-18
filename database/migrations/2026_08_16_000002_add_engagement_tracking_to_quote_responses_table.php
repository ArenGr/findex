<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two things the request status page can't honestly say without them.
 *
 * viewed_at is the difference between "we sent this to five agencies" and
 * "three of them have actually opened it" - without it, a status page
 * claiming agencies are reviewing a request would be inventing the middle
 * step, which is exactly the fake progress the design must not show.
 *
 * valid_until is how long the agency is willing to hold the offer. It sits
 * on the response rather than on each suggestion because an agency quotes
 * one deadline for the whole reply, however many options it contains.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_responses', function (Blueprint $table) {
            $table->timestamp('viewed_at')->nullable()->after('status');
            $table->timestamp('valid_until')->nullable()->after('responded_at');

            // The agency inbox lists this organization's rows newest-first
            // and counts the ones still awaiting a reply.
            $table->index(['organization_id', 'status']);
        });

        // An agency that already replied plainly saw the request first. Left
        // null for pending rows rather than guessed - "we don't know" is the
        // honest answer there, and the status page treats it as such.
        DB::table('quote_responses')
            ->whereNotNull('responded_at')
            ->update(['viewed_at' => DB::raw('responded_at')]);
    }

    public function down(): void
    {
        Schema::table('quote_responses', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'status']);
            $table->dropColumn(['viewed_at', 'valid_until']);
        });
    }
};
