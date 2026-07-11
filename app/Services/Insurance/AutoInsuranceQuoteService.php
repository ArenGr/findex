<?php

namespace App\Services\Insurance;

use App\Models\AutoInsuranceQuote;
use App\Models\AutoInsuranceRequest;
use App\Models\Organization;

/**
 * Business logic (which partners to ask) kept separate from how a quote is
 * actually obtained (InsuranceQuoteProviderInterface) - the same split as
 * SendQuoteRequestToPartnersJob/PartnerNotifierInterface in the tourism flow.
 * Run synchronously (not queued) since the point of an API-based integration
 * is that quotes come back fast enough to show on the results page
 * immediately, unlike tourism's Telegram-and-wait flow.
 */
class AutoInsuranceQuoteService
{
    public function __construct(private readonly InsuranceQuoteProviderInterface $provider)
    {
    }

    public function requestQuotes(AutoInsuranceRequest $request): void
    {
        $partners = Organization::active()->where('type', 'insurance')->get();

        foreach ($partners as $partner) {
            $result = $this->provider->quote($request, $partner);

            AutoInsuranceQuote::create(array_merge($result, [
                'auto_insurance_request_id' => $request->id,
                'organization_id' => $partner->id,
                'responded_at' => now(),
            ]));
        }
    }
}
