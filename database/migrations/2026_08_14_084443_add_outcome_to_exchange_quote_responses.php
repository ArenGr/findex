<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // What actually happened after an offer was chosen.
    public function up(): void
    {
        Schema::table('exchange_quote_responses', function (Blueprint $table) {
            $table->string('outcome', 20)->nullable()->after('accepted_at');
            $table->timestamp('outcome_at')->nullable()->after('outcome');

            // Reporting reads "every completed exchange this month" far more often than it reads any one row.
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
