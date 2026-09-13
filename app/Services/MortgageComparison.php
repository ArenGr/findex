<?php

namespace App\Services;

use App\Enums\MortgageRateType;
use App\Models\MortgageOffer;
use App\Support\Mortgage\MortgageScenario;
use App\Support\Mortgage\RankedMortgage;
use Illuminate\Support\Collection;

// Ranks mortgage offers for a borrower, honestly.
class MortgageComparison
{
    public const SUBSIDIZED_CATEGORIES = ['young_family', 'nmc'];

    // How far a figure can drift from "now" before it is flagged stale.
    private const STALE_AFTER_DAYS = 45;

    // Trust order for where a figure came from, high to low.
    private const SOURCE_TIER_RANK = [
        'official_page' => 0,
        'official_pdf' => 1,
        'aggregator' => 2,
        'news' => 3,
    ];

    /**
     * Rank a set of candidate offers against one scenario.
     *
     * @param  Collection<int, MortgageOffer>  $offers
     * @return array{ranked: list<RankedMortgage>, incomplete: list<RankedMortgage>}
     */
    public function rank(Collection $offers, MortgageScenario $scenario): array
    {
        $evaluated = $offers
            ->filter(fn (MortgageOffer $offer) => $this->inCohort($offer, $scenario))
            ->map(fn (MortgageOffer $offer) => $this->evaluate($offer, $scenario))
            ->filter(fn (RankedMortgage $row) => $row->eligible)
            ->values();

        [$complete, $incomplete] = $evaluated->partition(fn (RankedMortgage $row) => $row->complete);

        $ranked = $complete->sort($this->comparator())->values()->all();

        return [
            'ranked' => $ranked,
            'incomplete' => $incomplete->values()->all(),
        ];
    }

    private function inCohort(MortgageOffer $offer, MortgageScenario $scenario): bool
    {
        return $offer->category === $scenario->category
            && $offer->currency === $scenario->currency;
    }

    private function evaluate(MortgageOffer $offer, MortgageScenario $scenario): RankedMortgage
    {
        $asOf = $scenario->asOf();
        $badges = [];

        // Effective rate: prefer the published APR, fall back to nominal.
        $rate = null;
        $basis = 'none';

        if ($offer->apr_min !== null) {
            $rate = (float) $offer->apr_min;
            $basis = 'apr';
            if ($offer->apr_max !== null && (float) $offer->apr_max > $rate) {
                $badges[] = 'rate_varies';
            }
        } elseif ($offer->interest_rate_min !== null) {
            $rate = (float) $offer->interest_rate_min;
            $basis = 'nominal';
            $badges[] = 'rate_only';
            if ($offer->interest_rate_max !== null && (float) $offer->interest_rate_max > $rate) {
                $badges[] = 'rate_varies';
            }
        }

        if ($offer->rate_type !== MortgageRateType::FIXED) {
            $badges[] = 'floating';
        }

        if (in_array($offer->category, self::SUBSIDIZED_CATEGORIES, true)) {
            $badges[] = 'subsidy';
        }

        $promoExpired = false;
        if ($offer->promo_ends_at !== null) {
            if ($offer->promo_ends_at->lt($asOf)) {
                $badges[] = 'promo_expired';
                $promoExpired = true;
            } else {
                $badges[] = 'promo';
            }
        }

        if ($offer->scraped_at !== null && $offer->scraped_at->lt($asOf->copy()->subDays(self::STALE_AFTER_DAYS))) {
            $badges[] = 'stale';
        }

        $eligible = $this->isEligible($offer, $scenario);

        [$monthly, $total] = $rate !== null
            ? $this->annuity($scenario->amount, $rate, $scenario->termMonths)
            : [null, null];

        // Rankable only with a rate, and not on an expired promotion.
        $complete = $rate !== null && ! $promoExpired;

        return new RankedMortgage(
            offer: $offer,
            effectiveRatePercent: $rate,
            rateBasis: $basis,
            monthlyPayment: $monthly,
            totalCost: $total,
            eligible: $eligible,
            complete: $complete,
            badges: $badges,
        );
    }

    // A missing constraint is not a disqualification - only a stated one the scenario violates.
    private function isEligible(MortgageOffer $offer, MortgageScenario $scenario): bool
    {
        if ($offer->min_amount !== null && $scenario->amount < (float) $offer->min_amount) {
            return false;
        }

        if ($offer->max_amount !== null && $scenario->amount > (float) $offer->max_amount) {
            return false;
        }

        if ($offer->term_min_months !== null && $scenario->termMonths < $offer->term_min_months) {
            return false;
        }

        if ($offer->term_max_months !== null && $scenario->termMonths > $offer->term_max_months) {
            return false;
        }

        if ($offer->min_down_payment_percent !== null
            && $scenario->downPaymentPercent !== null
            && $scenario->downPaymentPercent < (float) $offer->min_down_payment_percent) {
            return false;
        }

        return true;
    }

    /**
     * @return array{0: float, 1: float} [monthly payment, total cost]
     */
    private function annuity(float $principal, float $annualRatePercent, int $months): array
    {
        $monthlyRate = $annualRatePercent / 100 / 12;

        if ($monthlyRate <= 0.0) {
            $payment = $principal / $months;

            return [$payment, $payment * $months];
        }

        $payment = $principal * $monthlyRate / (1 - (1 + $monthlyRate) ** (-$months));

        return [$payment, $payment * $months];
    }

    private function comparator(): callable
    {
        return function (RankedMortgage $a, RankedMortgage $b): int {
            return $this->compareRate($a, $b)
                ?: $this->compareDownPayment($a, $b)
                ?: $this->compareTermCeiling($a, $b)
                ?: $this->compareRateType($a, $b)
                ?: $this->compareSourceTier($a, $b)
                ?: $this->compareFreshness($a, $b);
        };
    }

    private function compareRate(RankedMortgage $a, RankedMortgage $b): int
    {
        return $a->effectiveRatePercent <=> $b->effectiveRatePercent;
    }

    private function compareDownPayment(RankedMortgage $a, RankedMortgage $b): int
    {
        // A null (unstated) requirement sorts after a stated lower one.
        $da = $a->offer->min_down_payment_percent ?? INF;
        $db = $b->offer->min_down_payment_percent ?? INF;

        return (float) $da <=> (float) $db;
    }

    private function compareTermCeiling(RankedMortgage $a, RankedMortgage $b): int
    {
        return ($b->offer->term_max_months ?? 0) <=> ($a->offer->term_max_months ?? 0);
    }

    private function compareRateType(RankedMortgage $a, RankedMortgage $b): int
    {
        return $this->rateTypeWeight($a) <=> $this->rateTypeWeight($b);
    }

    private function rateTypeWeight(RankedMortgage $row): int
    {
        return $row->offer->rate_type === MortgageRateType::FIXED ? 0 : 1;
    }

    private function compareSourceTier(RankedMortgage $a, RankedMortgage $b): int
    {
        $ra = self::SOURCE_TIER_RANK[$a->offer->source_tier] ?? count(self::SOURCE_TIER_RANK);
        $rb = self::SOURCE_TIER_RANK[$b->offer->source_tier] ?? count(self::SOURCE_TIER_RANK);

        return $ra <=> $rb;
    }

    private function compareFreshness(RankedMortgage $a, RankedMortgage $b): int
    {
        return ($b->offer->scraped_at?->timestamp ?? 0) <=> ($a->offer->scraped_at?->timestamp ?? 0);
    }
}
