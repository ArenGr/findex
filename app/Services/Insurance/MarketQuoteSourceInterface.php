<?php

namespace App\Services\Insurance;

use App\Models\AutoInsuranceRequest;

/**
 * A source that answers for several insurers in one call, rather than for
 * one insurer at a time the way InsuranceQuoteProviderInterface does.
 *
 * That distinction exists because of how the market actually works here:
 * these calculators are all thin proxies over one Motor Insurers' Bureau
 * system, and at least one of them (Sil - see SilMarketQuoteSource) returns
 * the whole table rather than just its own row. One call can therefore price
 * insurers that have no integration of their own.
 *
 * Separate from the per-partner interface for a practical reason too: a
 * source like this needs details a plain quote does not (see
 * MarketQuoteDetails), so it only runs when the user has chosen to provide
 * them, and the rest of the flow has to work without it.
 */
interface MarketQuoteSourceInterface
{
    /**
     * @return array<string, string> organization slug => premium amount.
     *                               Insurers the source cannot positively
     *                               identify are omitted, so callers must
     *                               treat a missing slug as "no answer"
     *                               rather than as a decline.
     */
    public function premiums(
        AutoInsuranceRequest $request,
        QuoteIdentity $identity,
        MarketQuoteDetails $details,
    ): array;
}
