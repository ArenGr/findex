<?php

namespace Tests\Feature;

use App\Jobs\NotifyDestinationAlertsJob;
use App\Mail\DestinationNowAvailable;
use App\Models\DestinationAlert;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DestinationAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_subscribe_to_a_destination_alert(): void
    {
        $this->post(route('tourism.destination-alerts.store', ['locale' => 'en']), [
            'destination_country' => 'TH',
            'email' => 'guest@example.com',
        ])->assertRedirect();

        $this->assertDatabaseHas('destination_alerts', [
            'email' => 'guest@example.com',
            'destination_country' => 'TH',
        ]);
    }

    public function test_logged_in_user_does_not_need_to_type_their_email(): void
    {
        $user = User::factory()->create(['email' => 'customer@example.com']);

        $this->actingAs($user)->post(route('tourism.destination-alerts.store', ['locale' => 'en']), [
            'destination_country' => 'TH',
        ])->assertRedirect();

        $this->assertDatabaseHas('destination_alerts', [
            'user_id' => $user->id,
            'email' => 'customer@example.com',
            'destination_country' => 'TH',
        ]);
    }

    public function test_resubmitting_the_same_destination_does_not_duplicate(): void
    {
        $this->post(route('tourism.destination-alerts.store', ['locale' => 'en']), [
            'destination_country' => 'TH', 'email' => 'guest@example.com',
        ]);
        $this->post(route('tourism.destination-alerts.store', ['locale' => 'en']), [
            'destination_country' => 'TH', 'email' => 'guest@example.com',
        ]);

        $this->assertSame(1, DestinationAlert::where('email', 'guest@example.com')->count());
    }

    public function test_submitting_a_request_for_an_unserved_destination_offers_the_alert_form(): void
    {
        $response = $this->post(route('tourism.request.store', ['locale' => 'en']), [
            'destination_country' => 'TH',
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(17)->toDateString(),
            'adults' => 2,
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'consent' => '1',
        ]);

        $response->assertSessionHasErrors(['destination_country']);
    }

    public function test_new_destination_dispatches_the_notify_job(): void
    {
        Queue::fake();

        $organization = Organization::create([
            'name' => 'New Partner Agency', 'slug' => 'new-partner-agency', 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true,
        ]);
        $user = User::factory()->organization($organization)->create();

        $this->actingAs($user, 'organization')->put(route('org.dashboard.tourism.destinations.update', ['locale' => 'en']), [
            'destinations' => ['TH'],
        ])->assertRedirect();

        Queue::assertPushed(NotifyDestinationAlertsJob::class, fn ($job) => $job->countryCode === 'TH');
    }

    public function test_re_saving_the_same_destinations_does_not_redispatch_the_job(): void
    {
        $organization = Organization::create([
            'name' => 'Existing Partner Agency', 'slug' => 'existing-partner-agency', 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true,
        ]);
        $organization->tourismDestinations()->create(['country_code' => 'TH']);
        $user = User::factory()->organization($organization)->create();

        Queue::fake();

        $this->actingAs($user, 'organization')->put(route('org.dashboard.tourism.destinations.update', ['locale' => 'en']), [
            'destinations' => ['TH'],
        ])->assertRedirect();

        Queue::assertNotPushed(NotifyDestinationAlertsJob::class);
    }

    public function test_notify_job_emails_subscribers_and_clears_alerts_for_that_destination(): void
    {
        Mail::fake();
        DestinationAlert::create(['email' => 'a@example.com', 'destination_country' => 'TH', 'locale' => 'en']);
        DestinationAlert::create(['email' => 'b@example.com', 'destination_country' => 'TH', 'locale' => 'hy']);
        DestinationAlert::create(['email' => 'c@example.com', 'destination_country' => 'GE', 'locale' => 'en']);

        (new NotifyDestinationAlertsJob('TH'))->handle();

        Mail::assertSent(DestinationNowAvailable::class, 2);
        $this->assertSame(0, DestinationAlert::where('destination_country', 'TH')->count());
        $this->assertSame(1, DestinationAlert::where('destination_country', 'GE')->count());
    }
}
