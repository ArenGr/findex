<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The only index touching currency_id is the composite unique
     * (organization_id, currency_id, rate_type), which doesn't serve a
     * currency_id-first lookup - exactly what CheckRateAlerts::findMatchingRate()
     * and the public rates listing do. Without this, both degrade to a full
     * table scan as rate history grows.
     */
    public function up(): void
    {
        Schema::table('currency_rates', function (Blueprint $table) {
            $table->index(['currency_id', 'rate_type', 'scraped_at']);
        });
    }

    public function down(): void
    {
        Schema::table('currency_rates', function (Blueprint $table) {
            $table->dropIndex(['currency_id', 'rate_type', 'scraped_at']);
        });
    }
};
