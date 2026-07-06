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
        Schema::table('mortgage_offers', function (Blueprint $table) {
            // A lightweight label for now ('secondary_market', 'primary_market', ...)
            // so offers can't be compared across incompatible product types.
            // Not yet a full categories table - only one category is populated
            // until there's enough cross-bank data to design that properly.
            $table->string('category')->nullable()->after('rate_type');
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mortgage_offers', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};
