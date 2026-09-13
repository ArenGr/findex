<?php

namespace App\Support\Mortgage;

use App\Models\MortgageOffer;

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
