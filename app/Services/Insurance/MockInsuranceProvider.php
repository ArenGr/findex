<?php

namespace App\Services\Insurance;

use App\Models\AutoInsuranceQuote;
use App\Models\AutoInsuranceRequest;
use App\Models\Organization;

/**
 * Stands in for real per-partner insurance APIs, which don't exist yet -
 * this generates a plausible, deterministic premium so the request/results
 * flow can be demoed to partners end to end. Deterministic (no randomness)
 * so the same request always reproduces the same quotes: variance across
 * partners comes from their organization id, not chance, so a demo never
 * looks flaky on a re-run.
 */
class MockInsuranceProvider implements InsuranceQuoteProviderInterface
{
    // Compulsory motor TPL in Armenia has one fixed product (no
    // comprehensive/third-party choice) - the base annual rate mainly
    // differs by whether the vehicle is privately owned or belongs to a
    // legal entity (which typically carries a commercial-use surcharge).
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

    // Compulsory motor TPL in Armenia really does rate on engine power -
    // a bigger engine is a bigger payout risk. Upper bound of each band.
    private const ENGINE_POWER_BANDS = [
        70 => 0.85,
        100 => 1.0,
        120 => 1.1,
        150 => 1.25,
        200 => 1.45,
    ];

    private const ENGINE_POWER_TOP_FACTOR = 1.7;

    // A newly licensed driver is a bigger risk than someone with a decade
    // behind the wheel - real insurers price this in, this doesn't yet.
    private const EXPERIENCE_FACTOR = [
        2 => 1.15,
        6 => 1.05,
        10 => 1.0,
    ];

    private const EXPERIENCE_TOP_FACTOR = 0.95;

    /**
     * Bonus-malus: each consecutive accident-free year earns a discount,
     * capped so a lifetime of clean driving doesn't imply a free policy.
     */
    private const ACCIDENT_FREE_DISCOUNT_PER_YEAR = 0.03;
    private const ACCIDENT_FREE_MAX_YEARS = 5;

    public function quote(AutoInsuranceRequest $request, Organization $partner): array
    {
        $base = self::BASE_ANNUAL_PREMIUM[$request->owner_type];
        $termFactor = self::TERM_FACTOR[$request->contract_term_months];
        $engineFactor = $this->engineFactor($request->engine_power_hp);
        $experienceFactor = $this->experienceFactor($request->driver_experience_years);
        $bonusMalusFactor = $this->bonusMalusFactor($request->accident_free_years);

        // Stand-in for each partner's own real-world rate differences -
        // deterministic from the partner's id so quotes stay stable across
        // page reloads instead of reshuffling on every request.
        $partnerVariance = 0.85 + ($partner->id % 7) * 0.05;

        $premium = (int) round(
            $base * $termFactor * $engineFactor * $experienceFactor * $bonusMalusFactor * $partnerVariance / 1000
        ) * 1000;

        // Each partner also gets a distinct coverage/perks pitch, again
        // picked deterministically by id - real insurers won't all phrase
        // their product the same way, and identical boilerplate across
        // every card would give the game away when demoing this to them.
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
