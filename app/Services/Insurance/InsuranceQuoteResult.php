<?php

namespace App\Services\Insurance;

use App\Models\AutoInsuranceQuote;
use App\Models\AutoInsuranceRequest;
use App\Models\Organization;
use Illuminate\Support\Facades\Log;

final class InsuranceQuoteResult
{
    private const CURRENCY = 'AMD';

    /**
     * @param  mixed  $premium  whatever the insurer returned as a price -
     *                          numeric string, number, or nothing at all
     * @return array<string, mixed>
     */
    public static function from(
        $premium,
        AutoInsuranceRequest $request,
        Organization $partner,
        string $providerSlug,
        int $status,
    ): array {
        if (! is_numeric($premium) || $premium <= 0) {
            return self::declined($partner, $providerSlug, $status);
        }

        return [
            'status' => AutoInsuranceQuote::STATUS_QUOTED,
            'premium_amount' => number_format((float) $premium, 2, '.', ''),
            'premium_currency' => self::CURRENCY,
            'policy_term_months' => $request->contract_term_months,
            'coverage_summary' => null,
            'notes' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function declined(Organization $partner, string $providerSlug, int $status): array
    {
        Log::warning('Insurance quote unavailable', [
            'provider' => $providerSlug,
            'organization_id' => $partner->id,
            // A status code and nothing else.
            'status' => $status,
        ]);

        return [
            'status' => AutoInsuranceQuote::STATUS_DECLINED,
            'premium_amount' => null,
            'premium_currency' => null,
            'policy_term_months' => null,
            'coverage_summary' => null,
            'notes' => null,
        ];
    }
}
