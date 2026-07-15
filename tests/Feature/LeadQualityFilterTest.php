<?php

namespace Tests\Feature;

use App\Jobs\SendQuoteRequestToPartnersJob;
use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Services\Notifications\PartnerNotifierInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadQualityFilterTest extends TestCase
{
    use RefreshDatabase;

    private function partner(array $overrides = []): Organization
    {
        $organization = Organization::create(array_merge([
            'name' => 'Filter Test Agency', 'slug' => 'filter-test-agency-' . uniqid(), 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true, 'telegram_chat_id' => '111',
        ], $overrides));
        $organization->tourismDestinations()->create(['country_code' => 'GE']);
        User::factory()->organization($organization)->create();

        return $organization;
    }

    private function quoteRequest(array $overrides = []): QuoteRequest
    {
        return QuoteRequest::create(array_merge([
            'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'locale' => 'en',
            'destination_country' => 'GE', 'check_in' => now()->addDays(10), 'check_out' => now()->addDays(17),
            'adults' => 2, 'children' => 0, 'all_inclusive' => false, 'insurance' => false,
            'expires_at' => now()->addDays(14),
        ], $overrides));
    }

    public function test_org_without_thresholds_receives_every_lead(): void
    {
        $this->partner();

        $notifier = $this->mock(PartnerNotifierInterface::class);
        $notifier->shouldReceive('notify')->once()->andReturn(true);

        SendQuoteRequestToPartnersJob::dispatchSync($this->quoteRequest(['budget_max_amd' => null]));
    }

    public function test_org_with_min_budget_excludes_a_lead_with_no_stated_budget(): void
    {
        $this->partner(['min_lead_budget_amd' => 300000]);

        $notifier = $this->mock(PartnerNotifierInterface::class);
        $notifier->shouldNotReceive('notify');

        SendQuoteRequestToPartnersJob::dispatchSync($this->quoteRequest(['budget_max_amd' => null]));
    }

    public function test_org_with_min_budget_excludes_a_lead_below_it(): void
    {
        $this->partner(['min_lead_budget_amd' => 300000]);

        $notifier = $this->mock(PartnerNotifierInterface::class);
        $notifier->shouldNotReceive('notify');

        SendQuoteRequestToPartnersJob::dispatchSync($this->quoteRequest(['budget_max_amd' => 100000]));
    }

    public function test_org_with_min_budget_receives_a_lead_meeting_it(): void
    {
        $this->partner(['min_lead_budget_amd' => 300000]);

        $notifier = $this->mock(PartnerNotifierInterface::class);
        $notifier->shouldReceive('notify')->once()->andReturn(true);

        SendQuoteRequestToPartnersJob::dispatchSync($this->quoteRequest(['budget_max_amd' => 500000]));
    }

    public function test_org_with_min_party_size_excludes_a_smaller_party(): void
    {
        $this->partner(['min_lead_party_size' => 4]);

        $notifier = $this->mock(PartnerNotifierInterface::class);
        $notifier->shouldNotReceive('notify');

        SendQuoteRequestToPartnersJob::dispatchSync($this->quoteRequest(['adults' => 2, 'children' => 0]));
    }

    public function test_org_with_min_party_size_receives_a_matching_party(): void
    {
        $this->partner(['min_lead_party_size' => 4]);

        $notifier = $this->mock(PartnerNotifierInterface::class);
        $notifier->shouldReceive('notify')->once()->andReturn(true);

        SendQuoteRequestToPartnersJob::dispatchSync($this->quoteRequest(['adults' => 2, 'children' => 2]));
    }

    public function test_org_can_set_lead_preferences_from_the_dashboard(): void
    {
        $organization = $this->partner();
        $user = User::factory()->organization($organization)->create();

        $this->actingAs($user, 'organization')->put(route('org.dashboard.tourism.lead-preferences.update', ['locale' => 'en']), [
            'min_lead_budget_amd' => 250000,
            'min_lead_party_size' => 3,
        ])->assertRedirect();

        $organization->refresh();
        $this->assertEquals(250000, $organization->min_lead_budget_amd);
        $this->assertSame(3, $organization->min_lead_party_size);
    }

    public function test_clearing_lead_preferences_removes_the_filter(): void
    {
        $organization = $this->partner(['min_lead_budget_amd' => 300000, 'min_lead_party_size' => 4]);
        $user = User::factory()->organization($organization)->create();

        $this->actingAs($user, 'organization')->put(route('org.dashboard.tourism.lead-preferences.update', ['locale' => 'en']), [])
            ->assertRedirect();

        $organization->refresh();
        $this->assertNull($organization->min_lead_budget_amd);
        $this->assertNull($organization->min_lead_party_size);
    }
}
