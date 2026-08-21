<?php

namespace App\Services\Insurance;

use App\Models\AutoInsuranceQuote;
use App\Models\AutoInsuranceRequest;
use App\Models\Organization;
use Illuminate\Support\Facades\Log;

/**
 * Turns "whatever the insurer said" into the row AutoInsuranceQuote stores,
 * so each provider only has to find the premium in its own response shape
 * and hand it over.
 *
 * Centralised mainly for the logging: a provider that failed is worth a log
 * line, and a log line written per-provider is a log line where somebody
 * eventually interpolates the plate or the ID number "just to debug it". The
 * only free-text this writes is a reason string built from a status code.
 */
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
            // A status code and nothing else. Never an exception message,
            // which would carry the request URL - see InsuranceHttpClient.
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
