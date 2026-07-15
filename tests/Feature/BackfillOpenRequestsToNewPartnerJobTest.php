<?php

namespace Tests\Feature;

use App\Jobs\BackfillOpenRequestsToNewPartnerJob;
use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Models\User;
use App\Services\Notifications\PartnerNotifierInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Covers reaching customers who already have an *open* request for a
 * destination when a new agency starts serving it - the complementary
 * case to NotifyDestinationAlertsJob, which only reaches people who
 * explicitly subscribed for an alert (see DestinationAlertTest).
 */
class BackfillOpenRequestsToNewPartnerJobTest extends TestCase
{
    use RefreshDatabase;

    private function openRequest(array $overrides = []): QuoteRequest
    {
        return QuoteRequest::create(array_merge([
            'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'locale' => 'en',
            'destination_country' => 'TH', 'check_in' => now()->addDays(10), 'check_out' => now()->addDays(17),
            'adults' => 2, 'children' => 0, 'all_inclusive' => false, 'insurance' => false,
            'expires_at' => now()->addDays(14),
        ], $overrides));
    }

    /**
     * The org already has a tourismDestinations row for TH by the time this
     * job runs in real usage - it's created in
     * TourismController::updateDestinations() before the job is dispatched.
     */
    private function newPartner(array $overrides = []): Organization
    {
        $organization = Organization::create(array_merge([
            'name' => 'New Thailand Agency', 'slug' => 'new-thailand-agency-' . uniqid(), 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true, 'telegram_chat_id' => '777',
        ], $overrides));
        $organization->tourismDestinations()->create(['country_code' => 'TH']);
        User::factory()->organization($organization)->create();

        return $organization;
    }

    public function test_creates_a_pending_response_for_every_open_request_in_that_destination(): void
    {
        $this->mock(PartnerNotifierInterface::class)->shouldReceive('notify')->once()->andReturn(true);

        $quoteRequest = $this->openRequest();
        $organization = $this->newPartner();

        (new BackfillOpenRequestsToNewPartnerJob($organization->id, 'TH'))->handle(app(PartnerNotifierInterface::class));

        $this->assertDatabaseHas('quote_responses', [
            'quote_request_id' => $quoteRequest->id,
            'organization_id' => $organization->id,
            'status' => QuoteResponse::STATUS_PENDING,
        ]);
    }

    public function test_ignores_expired_requests(): void
    {
        $this->mock(PartnerNotifierInterface::class)->shouldNotReceive('notify');

        $quoteRequest = $this->openRequest(['expires_at' => now()->subDay()]);
        $organization = $this->newPartner();

        (new BackfillOpenRequestsToNewPartnerJob($organization->id, 'TH'))->handle(app(PartnerNotifierInterface::class));

        $this->assertDatabaseMissing('quote_responses', [
            'quote_request_id' => $quoteRequest->id,
            'organization_id' => $organization->id,
        ]);
    }

    public function test_ignores_requests_outside_the_destination(): void
    {
        $this->mock(PartnerNotifierInterface::class)->shouldNotReceive('notify');

        $quoteRequest = $this->openRequest(['destination_country' => 'GE']);
        $organization = $this->newPartner();

        (new BackfillOpenRequestsToNewPartnerJob($organization->id, 'TH'))->handle(app(PartnerNotifierInterface::class));

        $this->assertDatabaseMissing('quote_responses', [
            'quote_request_id' => $quoteRequest->id,
            'organization_id' => $organization->id,
        ]);
    }

    public function test_skips_a_request_that_already_has_a_response_from_this_org(): void
    {
        $this->mock(PartnerNotifierInterface::class)->shouldNotReceive('notify');

        $quoteRequest = $this->openRequest();
        $organization = $this->newPartner();
        $quoteRequest->responses()->create([
            'organization_id' => $organization->id,
            'response_token' => 'existing-token',
            'status' => QuoteResponse::STATUS_PENDING,
        ]);

        (new BackfillOpenRequestsToNewPartnerJob($organization->id, 'TH'))->handle(app(PartnerNotifierInterface::class));

        $this->assertSame(1, QuoteResponse::where('quote_request_id', $quoteRequest->id)->count());
    }

    public function test_respects_the_orgs_own_lead_quality_filters(): void
    {
        $this->mock(PartnerNotifierInterface::class)->shouldNotReceive('notify');

        $quoteRequest = $this->openRequest(['budget_max_amd' => 200000]);
        $organization = $this->newPartner(['min_lead_budget_amd' => 500000]);

        (new BackfillOpenRequestsToNewPartnerJob($organization->id, 'TH'))->handle(app(PartnerNotifierInterface::class));

        $this->assertDatabaseMissing('quote_responses', [
            'quote_request_id' => $quoteRequest->id,
            'organization_id' => $organization->id,
        ]);
    }

    public function test_updating_destinations_dispatches_the_backfill_job(): void
    {
        Queue::fake();

        $organization = Organization::create([
            'name' => 'Dispatch Test Agency', 'slug' => 'dispatch-test-agency', 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true,
        ]);
        $user = User::factory()->organization($organization)->create();

        $this->actingAs($user, 'organization')->put(route('org.dashboard.tourism.destinations.update', ['locale' => 'en']), [
            'destinations' => ['TH'],
        ])->assertRedirect();

        Queue::assertPushed(
            BackfillOpenRequestsToNewPartnerJob::class,
            fn ($job) => $job->organizationId === $organization->id && $job->countryCode === 'TH'
        );
    }
}
