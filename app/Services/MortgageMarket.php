<?php

namespace App\Services;

use App\Models\MortgageOffer;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The benchmark / "market context" tier, in the NerdWallet mould: before a
 * visitor reads any single bank's offer, a headline average and a
 * product-by-product overview tell them what the market looks like today, so
 * an individual rate can be read as cheap or dear rather than in a vacuum.
 *
 * Averages are computed from the same published offers the ranker uses,
 * over each offer's effective rate (APR when the bank publishes it, nominal
 * otherwise). Expired promotions are excluded - they aren't part of "today's"
 * market. This is a benchmark of the rates WE collect, not a survey index,
 * and is labelled as such in the UI.
 */
class MortgageMarket
{
    /**
     * A headline benchmark for one cohort: how many offers, their average
     * effective rate, the cheapest, and how fresh the data is.
     *
     * @param  Collection<int, MortgageOffer>  $offers
     * @return array{count: int, avg_rate: ?float, min_rate: ?float, as_of: ?CarbonInterface}
     */
    public function benchmark(Collection $offers): array
    {
        $rated = $this->rated($offers);
        $rates = $rated->map(fn (MortgageOffer $offer) => $this->effectiveRate($offer));

        return [
            'count' => $rated->count(),
            'avg_rate' => $rates->isNotEmpty() ? round($rates->avg(), 2) : null,
            'min_rate' => $rates->isNotEmpty() ? round($rates->min(), 2) : null,
            'as_of' => $rated->max(fn (MortgageOffer $offer) => $offer->scraped_at),
        ];
    }

    /**
     * A "today's average rates" table: one benchmark row per
     * (currency, category) present in the data, so all products are visible
     * at a glance. Ordered cheapest-average first within the preferred
     * currency order.
     *
     * @param  Collection<int, MortgageOffer>  $offers
     * @return list<array{currency: string, category: string, count: int, avg_rate: float, min_rate: float}>
     */
    public function overview(Collection $offers): array
    {
        $currencyOrder = ['AMD' => 0, 'USD' => 1, 'EUR' => 2];

        return $this->rated($offers)
            ->groupBy(fn (MortgageOffer $offer) => $offer->currency.'|'.$offer->category)
            ->map(function (Collection $group) {
                $rates = $group->map(fn (MortgageOffer $offer) => $this->effectiveRate($offer));

                return [
                    'currency' => $group->first()->currency,
                    'category' => $group->first()->category,
                    'count' => $group->count(),
                    'avg_rate' => round($rates->avg(), 2),
                    'min_rate' => round($rates->min(), 2),
                ];
            })
            ->sortBy(fn ($row) => $row['avg_rate'])
            ->sortBy(fn ($row) => $currencyOrder[$row['currency']] ?? 99)
            ->values()
            ->all();
    }

    /**
     * Offers with a usable rate whose promo (if any) hasn't lapsed.
     *
     * @param  Collection<int, MortgageOffer>  $offers
     * @return Collection<int, MortgageOffer>
     */
    private function rated(Collection $offers): Collection
    {
        $now = CarbonImmutable::now();

        return $offers->filter(function (MortgageOffer $offer) use ($now) {
            if ($this->effectiveRate($offer) === null) {
                return false;
            }

            return $offer->promo_ends_at === null || $offer->promo_ends_at->gte($now);
        });
    }

    private function effectiveRate(MortgageOffer $offer): ?float
    {
        $rate = $offer->apr_min ?? $offer->interest_rate_min;

        // A zero (or missing) figure isn't a real published rate - the same
        // rule the scraper applies when it skips rate <= 0.
        return $rate !== null && (float) $rate > 0 ? (float) $rate : null;
    }
}
