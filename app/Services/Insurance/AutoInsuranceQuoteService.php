<?php

namespace App\Services\Insurance;

use App\Models\AutoInsuranceQuote;
use App\Models\AutoInsuranceRequest;
use App\Models\Organization;

/**
 * Prices every insurer from a single source: Sil's calculator, which asks the
 * Motor Insurers' Bureau for the whole market and returns one premium per
 * insurer (see SilMarketQuoteSource). One call, six insurers, mapped to our
 * organizations by the Bureau's icId - so there is no per-insurer integration
 * to keep working, and no place for one insurer's quirk to break the rest.
 *
 * The trade this makes is deliberate: Sil will not return premiums without a
 * bank account (its premium step validates the account's bank code), so the
 * request form has to collect one. That is why MarketQuoteDetails is required
 * here rather than optional - without it there is nothing to quote.
 *
 * Run synchronously, and that is not only about showing results immediately:
 * a queued job's payload is serialized into the jobs table in plain text, and
 * this call carries the owner's ID number and their bank account. Both refuse
 * to serialize for exactly that reason (see QuoteIdentity, MarketQuoteDetails),
 * so moving this onto a queue fails loudly rather than quietly logging them to
 * the database.
 *
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
        // One call prices the whole market. A user-input problem (bad ID,
        // unknown bank, malformed email) is thrown out of here as an
        // InsuranceQuoteInputException and handled on the form; anything
        // else comes back as simply "no premium for this insurer".
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
