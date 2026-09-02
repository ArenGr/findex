<?php

namespace Database\Seeders;

use App\Models\MortgageOffer;
use App\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * A one-time, manually-collected snapshot of AMD secondary-market (open
 * home-purchase) mortgage offers across Armenian banks, gathered 2026-08-25
 * from each bank's own pages / PDFs (and, where a site blocked automated
 * access, a clearly-tagged aggregator or news source).
 *
 * This is a STOP-GAP so the comparison has data for more than the two banks
 * that have real parsers (ACBA, Ameria). It is NOT wired into the default
 * DatabaseSeeder, so a real scrape never gets overwritten by it - run it
 * deliberately (`db:seed --class=CollectedMortgageOfferSeeder`) and let the
 * per-bank parsers supersede each row as they land. `source_tier` records
 * how trustworthy each figure is, and the ranker demotes / badges the weaker
 * ones accordingly (aggregator/news, expired promos, rate-only, floating).
 *
 * Only figures actually collected are included; nothing was invented, and
 * banks whose pages hard-blocked us with no usable secondary figure (AEB,
 * Converse) are deliberately absent.
 */
class CollectedMortgageOfferSeeder extends Seeder
{
    /**
     * @var list<array<string, mixed>>
     */
    private const OFFERS = [
        // slug, rate_type, nominal min/max, apr min/max, term_max_months,
        // min_down%, min/max amount (AMD), source_tier, promo_ends_at, url
        ['armswissbank', 'fixed', 13.0, 14.0, 9.37, 15.47, 240, 10, null, 100_000_000, 'official_page', null, 'https://www.armswissbank.am/en/individuals/fiz-loans/hipotek-loan/'],
        ['ameria', 'fixed', 13.0, 13.0, 11.80, 14.22, 240, 5, 3_000_000, 150_000_000, 'official_pdf', null, 'https://ameriabank.am/en'],
        ['byblos', 'floating_3y', 11.0, 12.5, 11.73, 15.85, 300, 20, 5_000_000, 250_000_000, 'official_page', null, 'https://www.byblosbankarmenia.am/en/loan/housing-loan/hl-acquisition'],
        ['ardshinbank', 'fixed', 11.7, 11.7, 12.30, 12.30, 240, 7.5, null, 200_000_000, 'news', '2026-04-27', 'https://ardshinbank.am'],
        ['idbank', 'fixed', 13.0, 16.5, 13.80, 18.10, 240, 10, 5_000_000, 60_000_000, 'official_page', null, 'https://idbank.am/en/credits/mortgage/for-the-purchase-national-mortgage-company/'],
        ['ineco', 'fixed', 12.5, 13.0, 13.81, 15.97, null, null, null, null, 'aggregator', '2026-09-16', 'https://www.inecobank.am'],
        ['vtb', 'floating_5y', 13.3, 13.3, 14.30, 14.30, 360, 10, 1_000_000, 200_000_000, 'official_page', null, 'https://www.vtb.am/en/credits/mortgage'],
        ['acba', 'fixed', 13.0, 14.5, 14.74, 14.74, 240, 10, 1_000_000, 500_000_000, 'official_page', null, 'https://acba.am/en/individual/loan/161'],
        ['artsakhbank', 'fixed', 14.5, 16.0, 15.58, 19.59, 240, null, 5_000_000, 75_000_000, 'official_page', null, 'https://www.artsakhbank.am/en/loans/mortgage-loans/real-estate-purchase'],
        // Nominal only (no APR published on the listing page) -> ranks on
        // nominal and is badged 'rate_only'.
        ['evoca', 'fixed', 13.2, 13.2, null, null, 240, null, null, 80_000_000, 'official_page', null, 'https://www.evoca.am/en/loans/mortgage-loans'],
        ['unibank', 'fixed', 13.3, 13.3, null, null, 240, 10, null, null, 'official_page', null, 'https://www.unibank.am/hy/service/mort/'],
        ['amio', 'fixed', 13.4, 13.4, null, null, 360, null, null, 150_000_000, 'official_page', null, 'https://amiobank.am/en/loans/mortgage-loans'],
    ];

    public function run(): void
    {
        $orgIds = Organization::pluck('id', 'slug');

        foreach (self::OFFERS as [$slug, $rateType, $rateMin, $rateMax, $aprMin, $aprMax, $termMax, $down, $minAmount, $maxAmount, $tier, $promoEndsAt, $url]) {
            if (! $orgId = $orgIds->get($slug)) {
                $this->command?->warn("Skipping mortgage snapshot: no organization '{$slug}'.");

                continue;
            }

            MortgageOffer::updateOrCreate(
                [
                    'organization_id' => $orgId,
                    'currency' => 'AMD',
                    'rate_type' => $rateType,
                    'category' => 'secondary_market',
                ],
                [
                    'interest_rate_min' => $rateMin,
                    'interest_rate_max' => $rateMax,
                    'apr_min' => $aprMin,
                    'apr_max' => $aprMax,
                    'term_min_months' => null,
                    'term_max_months' => $termMax,
                    'min_down_payment_percent' => $down,
                    'min_amount' => $minAmount,
                    'max_amount' => $maxAmount,
                    'source_url' => $url,
                    'source_tier' => $tier,
                    'promo_ends_at' => $promoEndsAt,
                    'scraped_at' => now(),
                ],
            );
        }

        $this->command?->info('Seeded '.count(self::OFFERS).' collected AMD secondary-market mortgage snapshots.');
    }
}
