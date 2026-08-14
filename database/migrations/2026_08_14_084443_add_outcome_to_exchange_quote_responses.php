<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What actually happened after an offer was chosen.
     *
     * This is the only place Findex can learn whether a request turned into a
     * real transaction: there is no bank affiliate link to follow and no
     * payment passing through us. The exchange office tells us, or nobody does.
     *
     * Separate from `status` on purpose. Status is where the offer got to with
     * us - pending, responded, accepted. Outcome is what happened in the shop,
     * and the two answer different questions: an accepted offer whose customer
     * never appeared is still accepted.
     */
    public function up(): void
    {
        Schema::table('exchange_quote_responses', function (Blueprint $table) {
            $table->string('outcome', 20)->nullable()->after('accepted_at');
            $table->timestamp('outcome_at')->nullable()->after('outcome');

            // Reporting reads "every completed exchange this month" far more
            // often than it reads any one row.
            $table->index(['outcome', 'outcome_at']);
        });
    }

    public function down(): void
    {
        Schema::table('exchange_quote_responses', function (Blueprint $table) {
            $table->dropIndex(['outcome', 'outcome_at']);
            $table->dropColumn(['outcome', 'outcome_at']);
        });
    }
};
