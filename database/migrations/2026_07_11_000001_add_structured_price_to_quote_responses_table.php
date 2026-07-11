<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A structured price, filled in either via the org dashboard's quote
     * form or parsed from a "Price: 610 USD"-style Telegram reply - lets the
     * results page show a confirmed figure instead of only ever guessing
     * one out of the agency's free-text reply_text.
     */
    public function up(): void
    {
        Schema::table('quote_responses', function (Blueprint $table) {
            $table->decimal('price_amount', 10, 2)->nullable()->after('reply_text');
            $table->string('price_currency', 3)->nullable()->after('price_amount');
        });
    }

    public function down(): void
    {
        Schema::table('quote_responses', function (Blueprint $table) {
            $table->dropColumn(['price_amount', 'price_currency']);
        });
    }
};
