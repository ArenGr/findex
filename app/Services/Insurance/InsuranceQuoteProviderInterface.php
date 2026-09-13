<?php

namespace App\Services\Insurance;

use App\Models\AutoInsuranceRequest;
use App\Models\Organization;

interface InsuranceQuoteProviderInterface
{
    /**
     * @return array{
     *     status: string,
     *     premium_amount: ?string,
     *     premium_currency: ?string,
     *     policy_term_months: ?int,
     *     coverage_summary: ?string,
     *     notes: ?string,
     * }
     *
     * @throws InsuranceQuoteInputException if the insurer rejects the plate/ID
     *                                      pair itself, which no other partner
     *                                      would accept either
     */
    public function quote(AutoInsuranceRequest $request, QuoteIdentity $identity, Organization $partner): array;
}
