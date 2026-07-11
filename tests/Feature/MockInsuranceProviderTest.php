<?php

namespace Tests\Feature;

use App\Models\AutoInsuranceQuote;
use App\Models\AutoInsuranceRequest;
use App\Models\Organization;
use App\Services\Insurance\MockInsuranceProvider;
use Tests\TestCase;

/**
 * Locks in the pricing behavior real partner APIs will eventually replace
 * (see InsuranceQuoteProviderInterface) - deterministic and always
 * successful, so demos never look flaky. MockInsuranceProvider::quote()
 * only reads model attributes and never touches the database, so these use
 * unsaved model instances with a forced id rather than persisting rows -
 * that keeps the variance formula's input fully under the test's control.
 */
class MockInsuranceProviderTest extends TestCase
{
    private function request(array $overrides = []): AutoInsuranceRequest
    {
        return AutoInsuranceRequest::make(array_merge([
            'locale' => 'en',
            'vehicle_plate' => '01AA123',
            'owner_type' => 'individual',
            'owner_id_number' => 'AN1234567',
            'contract_term_months' => 12,
        ], $overrides));
    }

    private function partner(int $id): Organization
    {
        return tap(new Organization(), fn (Organization $organization) => $organization->id = $id);
    }

    public function test_quote_is_always_successful_with_amd_currency(): void
    {
        $result = (new MockInsuranceProvider())->quote($this->request(), $this->partner(1));

        $this->assertSame(AutoInsuranceQuote::STATUS_QUOTED, $result['status']);
        $this->assertSame('AMD', $result['premium_currency']);
        $this->assertSame(12, $result['policy_term_months']);
    }

    public function test_legal_entity_owners_pay_more_than_individuals(): void
    {
        $individual = (new MockInsuranceProvider())->quote($this->request(['owner_type' => 'individual']), $this->partner(1));
        $legalEntity = (new MockInsuranceProvider())->quote($this->request(['owner_type' => 'legal_entity']), $this->partner(1));

        $this->assertGreaterThan((float) $individual['premium_amount'], (float) $legalEntity['premium_amount']);
    }

    public function test_shorter_contract_terms_cost_proportionally_more_per_month(): void
    {
        $threeMonths = (new MockInsuranceProvider())->quote($this->request(['contract_term_months' => 3]), $this->partner(1));
        $twelveMonths = (new MockInsuranceProvider())->quote($this->request(['contract_term_months' => 12]), $this->partner(1));

        $this->assertSame(3, $threeMonths['policy_term_months']);
        $this->assertSame(12, $twelveMonths['policy_term_months']);

        $threeMonthMonthlyRate = (float) $threeMonths['premium_amount'] / 3;
        $twelveMonthMonthlyRate = (float) $twelveMonths['premium_amount'] / 12;
        $this->assertGreaterThan($twelveMonthMonthlyRate, $threeMonthMonthlyRate);
    }

    public function test_different_partners_produce_different_but_deterministic_premiums(): void
    {
        $request = $this->request();
        $provider = new MockInsuranceProvider();

        $first = $provider->quote($request, $this->partner(1));
        $firstAgain = $provider->quote($request, $this->partner(1));
        $second = $provider->quote($request, $this->partner(2));

        $this->assertSame($first['premium_amount'], $firstAgain['premium_amount']);
        $this->assertNotSame($first['premium_amount'], $second['premium_amount']);
    }
}
