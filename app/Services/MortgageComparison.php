<?php

namespace App\Services;

use App\Enums\MortgageRateType;
use App\Models\MortgageOffer;
use App\Support\Mortgage\MortgageScenario;
use App\Support\Mortgage\RankedMortgage;
use Illuminate\Support\Collection;

/**
 * Ranks mortgage offers for a borrower, honestly.
 *
 * The three rules that keep the ranking meaningful:
 *   1. Never compare across products. Offers are ranked only within one
 *      (category, currency) cohort - a USD new-build loan and an AMD resale
 *      loan are different questions, and their headline rates aren't
 *      comparable.
 *   2. Rank on the effective rate (APR / փաստացի տոկոսադրույք), which folds
 *      in fees and mandatory insurance - not the headline nominal. Nominal
 *      is only a fallback, and offers ranked on it are badged 'rate_only'.
 *   3. Data quality is part of the ranking. An offer missing a rate can't be
 *      ranked at all (it goes to a separate "incomplete" list); an expired
 *      promo is excluded; a stale or low-tier figure is badged and demoted.
 *
 * Subsidised cohorts (young-family buy-down, NMC) are ranked in their own
 * cohort because eligibility gates who can take them - the offer row already
 * stores the post-buy-down rate, so no extra modelling is needed here. The
 * income-tax (IJEV) refund is deliberately NOT applied in this base ranker:
 * it depends on the borrower's tax paid, the property being primary-market,
 * the region, and the agreement date, so it belongs in an opt-in decorator,
 * not the default comparison.
 */
class MortgageComparison
{
    /**
     * Categories whose rate is only available to eligible borrowers, ranked
     * as their own cohort rather than mixed in with open-market products.
     */
    public const SUBSIDIZED_CATEGORIES = ['young_family', 'nmc'];

    /**
     * How far a figure can drift from "now" before it is flagged stale. A
     * daily scrape means anything older than this went unrefreshed - the
     * source probably changed shape or started blocking us.
     */
    private const STALE_AFTER_DAYS = 45;

    /**
     * Trust order for where a figure came from, high to low. A fresh
     * official page should never be outranked by a news snippet on a tie.
     */
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

    /**
     * A missing constraint is not a disqualification - only a stated one the
     * scenario violates. (An offer that doesn't publish its amount band is
     * not thereby ineligible; it just can't be checked on that axis.)
     */
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
     * Standard annuity: the level monthly payment that amortises `principal`
     * over `months` at the given annual rate, and the total paid across the
     * term. A zero rate degenerates to straight-line repayment.
     *
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

    /**
     * Cheapest effective rate first, then the tie-breakers in the order that
     * matters to a borrower: a lower required down payment, a longer term
     * ceiling (more flexibility), a fixed rate over a floating one, a more
     * trustworthy source, and finally the fresher figure.
     */
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
