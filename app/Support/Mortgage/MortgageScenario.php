<?php

namespace App\Support\Mortgage;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The borrower's situation a set of offers is ranked against. Every field
 * except the amount and term has a sensible default, so a page with no form
 * input can still rank on a representative standard scenario.
 */
final class MortgageScenario
{
    public function __construct(
        public readonly float $amount,
        public readonly int $termMonths,
        public readonly ?float $downPaymentPercent = null,
        public readonly string $currency = 'AMD',
        public readonly string $category = 'secondary_market',
        public readonly ?CarbonInterface $asOf = null,
    ) {}

    /**
     * A neutral default scenario for ranking when the visitor has entered
     * nothing: a mid-size AMD secondary-market purchase over 20 years with a
     * 30% down payment. Kept as the one place that figure is defined.
     */
    public static function standard(string $currency = 'AMD', string $category = 'secondary_market'): self
    {
        return new self(
            amount: 30_000_000,
            termMonths: 240,
            downPaymentPercent: 30,
            currency: $currency,
            category: $category,
        );
    }

    public function asOf(): CarbonInterface
    {
        return $this->asOf ?? CarbonImmutable::now();
    }
}
