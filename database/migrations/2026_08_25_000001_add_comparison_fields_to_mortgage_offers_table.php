<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mortgage_offers', function (Blueprint $table) {
            $table->decimal('apr_min', 5, 2)->nullable()->after('interest_rate_max');
            $table->decimal('apr_max', 5, 2)->nullable()->after('apr_min');
            // 'official_page' | 'official_pdf' | 'aggregator' | 'news'
            $table->string('source_tier')->nullable()->after('source_url');
            $table->date('promo_ends_at')->nullable()->after('source_tier');
        });
    }

    public function down(): void
    {
        Schema::table('mortgage_offers', function (Blueprint $table) {
            $table->dropColumn(['apr_min', 'apr_max', 'source_tier', 'promo_ends_at']);
        });
    }
};
