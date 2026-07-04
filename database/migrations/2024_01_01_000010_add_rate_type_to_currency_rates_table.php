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
        Schema::table('currency_rates', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'currency_id']);
            $table->string('rate_type')->default('cash')->after('currency_id');
            $table->unique(['organization_id', 'currency_id', 'rate_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currency_rates', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'currency_id', 'rate_type']);
            $table->dropColumn('rate_type');
            $table->unique(['organization_id', 'currency_id']);
        });
    }
};
