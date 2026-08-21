<?php

namespace Tests\Feature;

use App\Models\AutoInsuranceQuote;
use App\Models\AutoInsuranceRequest;
use App\Models\Organization;
use App\Services\Insurance\MockInsuranceProvider;
use App\Services\Insurance\QuoteIdentity;
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
    /**
     * MockInsuranceProvider ignores the identity - it has no registry to look
     * anything up in - but the interface requires one, so this stands in.
     */
    private static function identity(): QuoteIdentity
    {
        return new QuoteIdentity('01AA123', 'AN1234567');
    }

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
        return tap(new Organization, fn (Organization $organization) => $organization->id = $id);
    }

    public function test_quote_is_always_successful_with_amd_currency(): void
    {
        $result = (new MockInsuranceProvider)->quote($this->request(), self::identity(), $this->partner(1));

        $this->assertSame(AutoInsuranceQuote::STATUS_QUOTED, $result['status']);
        $this->assertSame('AMD', $result['premium_currency']);
        $this->assertSame(12, $result['policy_term_months']);
    }

    public function test_legal_entity_owners_pay_more_than_individuals(): void
    {
        $individual = (new MockInsuranceProvider)->quote($this->request(['owner_type' => 'individual']), self::identity(), $this->partner(1));
        $legalEntity = (new MockInsuranceProvider)->quote($this->request(['owner_type' => 'legal_entity']), self::identity(), $this->partner(1));

        $this->assertGreaterThan((float) $individual['premium_amount'], (float) $legalEntity['premium_amount']);
    }

    public function test_shorter_contract_terms_cost_proportionally_more_per_month(): void
    {
        $threeMonths = (new MockInsuranceProvider)->quote($this->request(['contract_term_months' => 3]), self::identity(), $this->partner(1));
        $twelveMonths = (new MockInsuranceProvider)->quote($this->request(['contract_term_months' => 12]), self::identity(), $this->partner(1));

        $this->assertSame(3, $threeMonths['policy_term_months']);
        $this->assertSame(12, $twelveMonths['policy_term_months']);

        $threeMonthMonthlyRate = (float) $threeMonths['premium_amount'] / 3;
        $twelveMonthMonthlyRate = (float) $twelveMonths['premium_amount'] / 12;
        $this->assertGreaterThan($twelveMonthMonthlyRate, $threeMonthMonthlyRate);
    }

    public function test_different_partners_produce_different_but_deterministic_premiums(): void
    {
        $request = $this->request();
        $provider = new MockInsuranceProvider;

        $first = $provider->quote($request, self::identity(), $this->partner(1));
        $firstAgain = $provider->quote($request, self::identity(), $this->partner(1));
        $second = $provider->quote($request, self::identity(), $this->partner(2));

        $this->assertSame($first['premium_amount'], $firstAgain['premium_amount']);
        $this->assertNotSame($first['premium_amount'], $second['premium_amount']);
    }

    public function test_a_more_powerful_engine_costs_more(): void
    {
        $weak = (new MockInsuranceProvider)->quote($this->request(['engine_power_hp' => 65]), self::identity(), $this->partner(1));
        $strong = (new MockInsuranceProvider)->quote($this->request(['engine_power_hp' => 220]), self::identity(), $this->partner(1));

        $this->assertGreaterThan((float) $weak['premium_amount'], (float) $strong['premium_amount']);
    }

    public function test_a_new_driver_pays_more_than_an_experienced_one(): void
    {
        $newDriver = (new MockInsuranceProvider)->quote($this->request(['driver_experience_years' => 1]), self::identity(), $this->partner(1));
        $veteran = (new MockInsuranceProvider)->quote($this->request(['driver_experience_years' => 15]), self::identity(), $this->partner(1));

        $this->assertGreaterThan((float) $veteran['premium_amount'], (float) $newDriver['premium_amount']);
    }

    public function test_more_accident_free_years_lowers_the_premium(): void
    {
        $noHistory = (new MockInsuranceProvider)->quote($this->request(['accident_free_years' => 0]), self::identity(), $this->partner(1));
        $cleanRecord = (new MockInsuranceProvider)->quote($this->request(['accident_free_years' => 5]), self::identity(), $this->partner(1));

        $this->assertGreaterThan((float) $cleanRecord['premium_amount'], (float) $noHistory['premium_amount']);
    }

    public function test_the_accident_free_discount_caps_at_five_years(): void
    {
        $fiveYears = (new MockInsuranceProvider)->quote($this->request(['accident_free_years' => 5]), self::identity(), $this->partner(1));
        $twentyYears = (new MockInsuranceProvider)->quote($this->request(['accident_free_years' => 20]), self::identity(), $this->partner(1));

        $this->assertSame($fiveYears['premium_amount'], $twentyYears['premium_amount']);
    }
}
