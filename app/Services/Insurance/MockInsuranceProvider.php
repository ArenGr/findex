<?php

namespace App\Services\Insurance;

use App\Models\AutoInsuranceQuote;
use App\Models\AutoInsuranceRequest;
use App\Models\Organization;

class MockInsuranceProvider implements InsuranceQuoteProviderInterface
{
    private const BASE_ANNUAL_PREMIUM = [
        'individual' => 25_000,
        'legal_entity' => 45_000,
    ];

    // Shorter terms cost proportionally more per month than a full year.
    private const TERM_FACTOR = [
        3 => 0.35,
        6 => 0.60,
        12 => 1.0,
    ];

    private const ENGINE_POWER_BANDS = [
        70 => 0.85,
        100 => 1.0,
        120 => 1.1,
        150 => 1.25,
        200 => 1.45,
    ];

    private const ENGINE_POWER_TOP_FACTOR = 1.7;

    private const EXPERIENCE_FACTOR = [
        2 => 1.15,
        6 => 1.05,
        10 => 1.0,
    ];

    private const EXPERIENCE_TOP_FACTOR = 0.95;

    private const ACCIDENT_FREE_DISCOUNT_PER_YEAR = 0.03;

    private const ACCIDENT_FREE_MAX_YEARS = 5;

    public function quote(AutoInsuranceRequest $request, QuoteIdentity $identity, Organization $partner): array
    {
        $base = self::BASE_ANNUAL_PREMIUM[$request->owner_type];
        $termFactor = self::TERM_FACTOR[$request->contract_term_months];
        $engineFactor = $this->engineFactor($request->engine_power_hp);
        $experienceFactor = $this->experienceFactor($request->driver_experience_years);
        $bonusMalusFactor = $this->bonusMalusFactor($request->accident_free_years);

        $partnerVariance = 0.85 + ($partner->id % 7) * 0.05;

        $premium = (int) round(
            $base * $termFactor * $engineFactor * $experienceFactor * $bonusMalusFactor * $partnerVariance / 1000
        ) * 1000;

        $coverageOptions = (array) __('auto_insurance.provider.coverage_summaries', [], $request->locale);
        $notesOptions = (array) __('auto_insurance.provider.quote_notes', [], $request->locale);

        return [
            'status' => AutoInsuranceQuote::STATUS_QUOTED,
            'premium_amount' => number_format($premium, 2, '.', ''),
            'premium_currency' => 'AMD',
            'policy_term_months' => $request->contract_term_months,
            'coverage_summary' => $coverageOptions[$partner->id % count($coverageOptions)],
            'notes' => $notesOptions[($partner->id + 1) % count($notesOptions)],
        ];
    }

    private function engineFactor(?int $enginePowerHp): float
    {
        if ($enginePowerHp === null) {
            return 1.0;
        }

        foreach (self::ENGINE_POWER_BANDS as $upperBound => $factor) {
            if ($enginePowerHp <= $upperBound) {
                return $factor;
            }
        }

        return self::ENGINE_POWER_TOP_FACTOR;
    }

    private function experienceFactor(?int $driverExperienceYears): float
    {
        if ($driverExperienceYears === null) {
            return 1.0;
        }

        foreach (self::EXPERIENCE_FACTOR as $upperBound => $factor) {
            if ($driverExperienceYears < $upperBound) {
                return $factor;
            }
        }

        return self::EXPERIENCE_TOP_FACTOR;
    }

    private function bonusMalusFactor(?int $accidentFreeYears): float
    {
        if ($accidentFreeYears === null) {
            return 1.0;
        }

        return 1 - min($accidentFreeYears, self::ACCIDENT_FREE_MAX_YEARS) * self::ACCIDENT_FREE_DISCOUNT_PER_YEAR;
    }
}
