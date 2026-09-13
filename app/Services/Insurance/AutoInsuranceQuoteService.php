<?php

namespace App\Services\Insurance;

use App\Models\AutoInsuranceQuote;
use App\Models\AutoInsuranceRequest;
use App\Models\Organization;

/**
 * @throws InsuranceQuoteInputException if Sil rejects the plate/ID/bank/email
 *                                      the user supplied - the caller turns
 *                                      that back into a form error
 */
class AutoInsuranceQuoteService
{
    public function __construct(private readonly MarketQuoteSourceInterface $market) {}

    public function requestQuotes(
        AutoInsuranceRequest $request,
        QuoteIdentity $identity,
        MarketQuoteDetails $details,
    ): void {
        // One call prices the whole market.
        $premiums = $this->market->premiums($request, $identity, $details);

        $partners = Organization::active()->where('type', 'insurance')->get();

        foreach ($partners as $partner) {
            $result = isset($premiums[$partner->slug])
                ? InsuranceQuoteResult::from($premiums[$partner->slug], $request, $partner, 'sil', 200)
                : InsuranceQuoteResult::declined($partner, 'sil', InsuranceHttpClient::STATUS_NO_RESPONSE);

            AutoInsuranceQuote::create(array_merge($result, [
                'auto_insurance_request_id' => $request->id,
                'organization_id' => $partner->id,
                'responded_at' => now(),
            ]));
        }
    }
}
