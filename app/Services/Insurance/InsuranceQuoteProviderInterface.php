<?php

namespace App\Services\Insurance;

use App\Models\AutoInsuranceRequest;
use App\Models\Organization;

/**
 * Seam between AutoInsuranceQuoteService (business logic: which partners to
 * ask) and however a quote actually gets fetched - mirrors
 * PartnerNotifierInterface's role in the tourism flow. MockInsuranceProvider
 * is the only implementation today; a real partner integration (see
 * RateParserFactory for the established per-partner-adapter pattern this
 * codebase already uses elsewhere) can replace it with zero changes to the
 * service or controller.
 */
interface InsuranceQuoteProviderInterface
{
    /**
     * @return array{
     *     status: string,
     *     premium_amount: ?string,
     *     premium_currency: ?string,
     *     deductible_amount: ?string,
     *     policy_term_months: ?int,
     *     coverage_summary: ?string,
     *     notes: ?string,
     * }
     */
    public function quote(AutoInsuranceRequest $request, Organization $partner): array;
}
