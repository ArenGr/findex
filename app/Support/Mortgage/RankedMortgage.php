<?php

namespace App\Support\Mortgage;

use App\Models\MortgageOffer;

/**
 * One offer evaluated against a scenario: the rate actually used to rank it,
 * where that rate came from, the resulting monthly payment and total cost,
 * whether the borrower is eligible, whether it has enough data to rank at
 * all, and the badges the UI should show ('floating', 'subsidy', 'promo',
 * 'stale', 'rate_only', ...).
 */
final class RankedMortgage
{
    /**
     * @param  list<string>  $badges
     */
    public function __construct(
        public readonly MortgageOffer $offer,
        public readonly ?float $effectiveRatePercent,
        public readonly string $rateBasis, // 'apr' | 'nominal' | 'none'
        public readonly ?float $monthlyPayment,
        public readonly ?float $totalCost,
        public readonly bool $eligible,
        public readonly bool $complete,
        public readonly array $badges,
    ) {}

    public function hasBadge(string $badge): bool
    {
        return in_array($badge, $this->badges, true);
    }
}
