<?php

namespace App\Services\Insurance;

use App\Models\AutoInsuranceRequest;
use App\Models\Organization;

/**
 * Seam between AutoInsuranceQuoteService (business logic: which partners to
 * ask) and however a quote actually gets fetched - mirrors
 * PartnerNotifierInterface's role in the tourism flow. IngoAppaProvider is
 * the first real implementation; MockInsuranceProvider still stands in for
 * every insurer without one yet, and InsuranceQuoteProviderFactory decides
 * which of them answers for a given partner.
 *
 * The identifiers arrive as a separate QuoteIdentity rather than as fields on
 * $request, because they are never stored - see that class for why, and for
 * the rules a provider has to keep to when handling them. The short version:
 * an implementation must not put a plate or an ID number into an exception,
 * a log line, or a queue payload.
 */
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
