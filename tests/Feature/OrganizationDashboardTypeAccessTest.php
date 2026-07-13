<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rates and Tourism are only relevant to a subset of org types (see
 * Organization::RATES_TYPES / TOURISM_TYPES) - this covers both the nav
 * link visibility and the route-level 'org.type' middleware enforcement, so
 * a mismatched org type can't reach the page just by hitting its URL.
 */
class OrganizationDashboardTypeAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrganizationUser(string $type): User
    {
        $organization = Organization::create([
            'name' => "Type Access Test {$type}",
            'slug' => "type-access-test-{$type}",
            'type' => $type,
            'country_code' => 'AM',
            'is_active' => true,
        ]);

        return User::factory()->organization($organization)->create([
            'email' => "type-access-{$type}@example.com",
        ]);
    }

    public function test_bank_can_access_rates_page(): void
    {
        $user = $this->makeOrganizationUser('bank');

        $this->actingAs($user, 'organization')
            ->get('/en/org/dashboard/rates')
            ->assertOk();
    }

    public function test_tourism_org_is_blocked_from_rates_page(): void
    {
        $user = $this->makeOrganizationUser('tourism');

        $this->actingAs($user, 'organization')
            ->get('/en/org/dashboard/rates')
            ->assertForbidden();
    }

    public function test_insurance_org_is_blocked_from_rates_page(): void
    {
        $user = $this->makeOrganizationUser('insurance');

        $this->actingAs($user, 'organization')
            ->get('/en/org/dashboard/rates')
            ->assertForbidden();
    }

    public function test_tourism_org_can_access_tourism_page(): void
    {
        $user = $this->makeOrganizationUser('tourism');

        $this->actingAs($user, 'organization')
            ->get('/en/org/dashboard/tourism')
            ->assertOk();
    }

    public function test_bank_is_blocked_from_tourism_page(): void
    {
        $user = $this->makeOrganizationUser('bank');

        $this->actingAs($user, 'organization')
            ->get('/en/org/dashboard/tourism')
            ->assertForbidden();
    }

    public function test_rates_link_hidden_for_insurance_but_shown_for_bank(): void
    {
        $bank = $this->makeOrganizationUser('bank');
        $insurance = $this->makeOrganizationUser('insurance');

        $this->actingAs($bank, 'organization')
            ->get('/en/org/dashboard')
            ->assertSee(route('org.dashboard.rates.index'), false);

        $this->actingAs($insurance, 'organization')
            ->get('/en/org/dashboard')
            ->assertDontSee(route('org.dashboard.rates.index'), false);
    }

    public function test_tourism_link_shown_only_for_tourism_org(): void
    {
        $tourism = $this->makeOrganizationUser('tourism');
        $bank = $this->makeOrganizationUser('bank');

        $this->actingAs($tourism, 'organization')
            ->get('/en/org/dashboard')
            ->assertSee(route('org.dashboard.tourism.index'), false);

        $this->actingAs($bank, 'organization')
            ->get('/en/org/dashboard')
            ->assertDontSee(route('org.dashboard.tourism.index'), false);
    }
}
