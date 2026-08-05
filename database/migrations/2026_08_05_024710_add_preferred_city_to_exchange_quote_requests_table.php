<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('exchange_quote_requests', function (Blueprint $table) {
            $table->string('preferred_city')->nullable()->after('rate_field');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exchange_quote_requests', function (Blueprint $table) {
            $table->dropColumn('preferred_city');
        });
    }
};
