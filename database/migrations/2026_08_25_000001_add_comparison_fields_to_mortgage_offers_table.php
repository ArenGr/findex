<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fields the comparison ranker needs but the scrape schema didn't yet
     * carry:
     *  - apr_min/apr_max: the legally-published "actual"/effective rate
     *    (փաստացի տոկոսադրույք). It folds in fees + mandatory insurance and
     *    is the only fair basis for ranking; banks show it one page deeper
     *    than the headline nominal, so it is captured separately.
     *  - source_tier: how trustworthy the figure is (an official page beats
     *    an official PDF beats an aggregator/news snippet). Feeds ranking so
     *    a stale news-derived rate never silently outranks a fresh official
     *    one.
     *  - promo_ends_at: many headline rates are time-boxed promotions; a
     *    ranker must be able to badge or drop an expired one.
     */
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
