<?php

namespace App\Support\Mortgage;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

// The borrower's situation a set of offers is ranked against.
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
