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

    public function quote(AutoInsuranceRequest $request, Organization $partner): array
    {
        $base = self::BASE_ANNUAL_PREMIUM[$request->owner_type];
        $termFactor = self::TERM_FACTOR[$request->contract_term_months];

        // Stand-in for each partner's own real-world rate differences -
        // deterministic from the partner's id so quotes stay stable across
        // page reloads instead of reshuffling on every request.
        $partnerVariance = 0.85 + ($partner->id % 7) * 0.05;

        $premium = (int) round($base * $termFactor * $partnerVariance / 1000) * 1000;

        return [
            'status' => AutoInsuranceQuote::STATUS_QUOTED,
            'premium_amount' => number_format($premium, 2, '.', ''),
            'premium_currency' => 'AMD',
            'policy_term_months' => $request->contract_term_months,
            'coverage_summary' => __('auto_insurance.provider.coverage_summary', [], $request->locale),
            'notes' => __('auto_insurance.provider.quote_notes', [], $request->locale),
        ];
    }
}
