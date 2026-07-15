<?php

namespace Tests\Feature;

use App\Models\AutoInsuranceQuote;
use App\Models\AutoInsuranceRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the insurance org dashboard - the one vertical that previously had
 * no dashboard page at all (see Organization::hasInsurancePage()). Quotes
 * are generated automatically (no per-request human response like tourism),
 * so this is read-only: quote history plus how many customers marked
 * interest (see AutoInsuranceController::markInterested).
 */
class OrganizationInsuranceDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function insuranceOrg(array $overrides = []): Organization
    {
        return Organization::create(array_merge([
            'name' => 'Dashboard Test Insurer', 'slug' => 'dashboard-test-insurer-' . uniqid(), 'type' => 'insurance',
            'country_code' => 'AM', 'is_active' => true,
        ], $overrides));
    }

    private function quote(Organization $organization, array $overrides = []): AutoInsuranceQuote
    {
        $request = AutoInsuranceRequest::create([
            'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'locale' => 'en',
            'vehicle_plate' => '01AA123', 'owner_type' => 'individual', 'owner_id_number' => 'AN1234567',
            'contract_term_months' => 12, 'engine_power_hp' => 100, 'driver_experience_years' => 8, 'accident_free_years' => 2,
        ]);

        return $request->quotes()->create(array_merge([
            'organization_id' => $organization->id,
            'status' => AutoInsuranceQuote::STATUS_QUOTED,
            'premium_amount' => 30000,
            'premium_currency' => 'AMD',
            'policy_term_months' => 12,
            'responded_at' => now(),
        ], $overrides));
    }

    public function test_insurance_org_can_see_its_own_quotes(): void
    {
        $organization = $this->insuranceOrg();
        $user = User::factory()->organization($organization)->create();
        $quote = $this->quote($organization);

        $response = $this->actingAs($user, 'organization')->get(route('org.dashboard.insurance.index', ['locale' => 'en']));

        $response->assertOk();
        $response->assertSee('01AA123');
        $response->assertSee('30000 AMD', false);
        $response->assertSee('Test Guest');
    }

    public function test_shows_interested_status_when_a_customer_marked_interest(): void
    {
        $organization = $this->insuranceOrg();
        $user = User::factory()->organization($organization)->create();
        $this->quote($organization, ['interested_at' => now()]);

        $response = $this->actingAs($user, 'organization')->get(route('org.dashboard.insurance.index', ['locale' => 'en']));

        $response->assertOk();
        $response->assertSee(__('org.insurance.total_quotes'));
        $response->assertSee('1'); // interested count
    }

    public function test_non_insurance_org_cannot_access_the_page(): void
    {
        $organization = Organization::create([
            'name' => 'Not An Insurer', 'slug' => 'not-an-insurer', 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true,
        ]);
        $user = User::factory()->organization($organization)->create();

        $this->actingAs($user, 'organization')
            ->get(route('org.dashboard.insurance.index', ['locale' => 'en']))
            ->assertForbidden();
    }

    public function test_nav_link_only_shows_for_insurance_orgs(): void
    {
        $insuranceOrg = $this->insuranceOrg();
        $insuranceUser = User::factory()->organization($insuranceOrg)->create();

        $bankOrg = Organization::create([
            'name' => 'Some Bank', 'slug' => 'some-bank-' . uniqid(), 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => true,
        ]);
        $bankUser = User::factory()->organization($bankOrg)->create();

        $this->actingAs($insuranceUser, 'organization')
            ->get(route('org.dashboard.index', ['locale' => 'en']))
            ->assertSee(__('org.nav.insurance'));

        $this->actingAs($bankUser, 'organization')
            ->get(route('org.dashboard.index', ['locale' => 'en']))
            ->assertDontSee(__('org.nav.insurance'));
    }

    public function test_shows_empty_state_with_no_quotes_yet(): void
    {
        $organization = $this->insuranceOrg();
        $user = User::factory()->organization($organization)->create();

        $response = $this->actingAs($user, 'organization')->get(route('org.dashboard.insurance.index', ['locale' => 'en']));

        $response->assertOk();
        $response->assertSee(__('org.insurance.no_quotes_yet'));
    }

    public function test_only_sees_its_own_quotes_not_another_orgs(): void
    {
        $organization = $this->insuranceOrg();
        $user = User::factory()->organization($organization)->create();
        $otherOrg = $this->insuranceOrg(['name' => 'Other Insurer']);
        $this->quote($otherOrg);

        $response = $this->actingAs($user, 'organization')->get(route('org.dashboard.insurance.index', ['locale' => 'en']));

        $response->assertOk();
        $response->assertSee(__('org.insurance.no_quotes_yet'));
    }
}
