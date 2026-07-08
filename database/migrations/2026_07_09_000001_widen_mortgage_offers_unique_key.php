<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The original unique key predates the `category` column
     * (2026_07_06_000012) and was never widened to include it, so two offers
     * that only differ by category (e.g. 'primary_market' vs
     * 'secondary_market') collide on updateOrCreate() and silently overwrite
     * each other. See App\Services\MortgageScraper, which now matches on
     * this same widened set of columns.
     *
     * The original index is dropped defensively (only if actually present)
     * rather than unconditionally - on at least one real environment it was
     * found already missing from the live schema despite its migration
     * being marked as run, which independently means mortgage_offers has
     * had no DB-level duplicate protection at all until this migration.
     */
    public function up(): void
    {
        try {
            Schema::table('mortgage_offers', function (Blueprint $table) {
                $table->dropUnique(['organization_id', 'currency', 'rate_type']);
            });
        } catch (\Throwable $e) {
            // Already missing on at least one real environment despite its
            // creating migration being marked as run - nothing to drop.
        }

        Schema::table('mortgage_offers', function (Blueprint $table) {
            // Explicit (short) name - MySQL's auto-generated name for this
            // column combination exceeds its 64-character identifier limit.
            $table->unique(['organization_id', 'currency', 'rate_type', 'category'], 'mortgage_offers_org_currency_type_category_unique');
        });
    }

    public function down(): void
    {
        Schema::table('mortgage_offers', function (Blueprint $table) {
            $table->dropUnique('mortgage_offers_org_currency_type_category_unique');
            $table->unique(['organization_id', 'currency', 'rate_type']);
        });
    }
};
