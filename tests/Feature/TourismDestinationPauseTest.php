<?php

namespace Tests\Feature;

use App\Jobs\SendQuoteRequestToPartnersJob;
use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Services\Notifications\PartnerNotifierInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers pausing/resuming a tourism destination: the dashboard toggle
 * itself, ownership scoping, and that SendQuoteRequestToPartnersJob
 * actually excludes paused destinations (with paused_until auto-resuming).
 */
class TourismDestinationPauseTest extends TestCase
{
    use RefreshDatabase;

    private function tourismOrgUser(string $countryCode = 'GE'): array
    {
        $organization = Organization::create([
            'name' => 'Pause Test Agency',
            'slug' => 'pause-test-agency-' . uniqid(),
            'type' => 'tourism',
            'country_code' => 'AM',
            'is_active' => true,
            'telegram_chat_id' => '111',
        ]);
        $destination = $organization->tourismDestinations()->create(['country_code' => $countryCode]);
        $user = User::factory()->organization($organization)->create();

        return [$organization, $destination, $user];
    }

    public function test_org_can_pause_its_own_destination_with_an_auto_resume_date(): void
    {
        [, $destination, $user] = $this->tourismOrgUser();
        $resumeDate = now()->addWeek()->toDateString();

        $this->actingAs($user, 'organization')->put(
            route('org.dashboard.tourism.destinations.pause', ['locale' => 'en', 'destination' => $destination->id]),
            ['is_paused' => '1', 'paused_until' => $resumeDate]
        )->assertRedirect();

        $destination->refresh();
        $this->assertTrue($destination->is_paused);
        $this->assertSame($resumeDate, $destination->paused_until->toDateString());
        $this->assertFalse($destination->isActive());
    }

    public function test_org_can_resume_a_paused_destination(): void
    {
        [, $destination, $user] = $this->tourismOrgUser();
        $destination->update(['is_paused' => true, 'paused_until' => null]);

        $this->actingAs($user, 'organization')->put(
            route('org.dashboard.tourism.destinations.pause', ['locale' => 'en', 'destination' => $destination->id]),
            ['is_paused' => '0']
        )->assertRedirect();

        $destination->refresh();
        $this->assertFalse($destination->is_paused);
        $this->assertTrue($destination->isActive());
    }

    public function test_org_cannot_pause_another_orgs_destination(): void
    {
        [, $destination] = $this->tourismOrgUser();
        [, , $otherUser] = $this->tourismOrgUser('EG');

        $this->actingAs($otherUser, 'organization')->put(
            route('org.dashboard.tourism.destinations.pause', ['locale' => 'en', 'destination' => $destination->id]),
            ['is_paused' => '1']
        )->assertNotFound();
    }

    public function test_paused_destination_with_no_resume_date_is_excluded_from_matching(): void
    {
        [$organization, $destination] = $this->tourismOrgUser('GE');
        $destination->update(['is_paused' => true, 'paused_until' => null]);

        $notifier = $this->mock(PartnerNotifierInterface::class);
        $notifier->shouldNotReceive('notify');

        SendQuoteRequestToPartnersJob::dispatchSync($this->quoteRequest());
    }

    public function test_destination_paused_until_a_past_date_is_treated_as_active_again(): void
    {
        [$organization, $destination] = $this->tourismOrgUser('GE');
        $destination->update(['is_paused' => true, 'paused_until' => now()->subDay()]);

        $notifier = $this->mock(PartnerNotifierInterface::class);
        $notifier->shouldReceive('notify')->once()->andReturn(true);

        SendQuoteRequestToPartnersJob::dispatchSync($this->quoteRequest());
    }

    private function quoteRequest(): QuoteRequest
    {
        return QuoteRequest::create([
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'locale' => 'en',
            'destination_country' => 'GE',
            'check_in' => now()->addDays(10),
            'check_out' => now()->addDays(17),
            'adults' => 2,
            'children' => 0,
            'all_inclusive' => false,
            'insurance' => false,
            'expires_at' => now()->addDays(14),
        ]);
    }
}
