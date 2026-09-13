<?php

namespace App\Services\Insurance;

use App\Models\AutoInsuranceRequest;

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
