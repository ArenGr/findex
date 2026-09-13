<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try {
            Schema::table('mortgage_offers', function (Blueprint $table) {
                $table->dropUnique(['organization_id', 'currency', 'rate_type']);
            });
        } catch (Throwable $e) {
        }

        Schema::table('mortgage_offers', function (Blueprint $table) {
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
