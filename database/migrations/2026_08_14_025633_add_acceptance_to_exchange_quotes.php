<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Accepting an offer, without telling the exchange office who accepted it.
    public function up(): void
    {
        Schema::table('exchange_quote_requests', function (Blueprint $table) {
            $table->string('public_code', 16)->nullable()->unique()->after('id');
        });

        Schema::table('exchange_quote_responses', function (Blueprint $table) {
            // The letter this response was given within its request.
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
