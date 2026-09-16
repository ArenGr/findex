<?php

namespace Tests\Feature;

use App\Mail\VisaOfferReceived;
use App\Mail\VisaRequestReceived;
use App\Mail\VisaRequestSubmitted;
use App\Models\FeatureToggle;
use App\Models\Organization;
use App\Models\User;
use App\Models\VisaRequest;
use App\Models\VisaResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class VisaRequestTest extends TestCase
{
    use RefreshDatabase;

    private function agency(string $name = 'Visa Agency', bool $withUser = false): Organization
    {
        $organization = Organization::factory()->create([
            'name' => $name,
            'type' => 'visa',
            'is_active' => true,
            'telegram_chat_id' => $withUser ? null : '123456',
        ]);

        if ($withUser) {
            User::factory()->create()->forceFill(['organization_id' => $organization->id])->save();
        }

        return $organization;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function submit(array $overrides = [])
    {
        return $this->post(route('visa.request.store', ['locale' => 'en']), array_merge([
            'destination_country' => 'DE',
            'travel_from' => now()->addMonth()->toDateString(),
            'travel_to' => now()->addMonth()->addWeek()->toDateString(),
            'applicants' => 2,
            'guest_name' => 'Ani Petrosyan',
            'guest_email' => 'ani@example.com',
            'consent' => '1',
        ], $overrides));
    }

    // Submitting fans out on the sync queue, so the agency's row already exists.
    private function pendingResponse(VisaRequest $visaRequest, Organization $organization): VisaResponse
    {
        return VisaResponse::query()
            ->where('visa_request_id', $visaRequest->id)
            ->where('organization_id', $organization->id)
            ->sole();
    }

    public function test_the_request_page_renders(): void
    {
        $this->get(route('visa.request', ['locale' => 'en']))
            ->assertOk()
            ->assertSee(__('visa.request.heading'));
    }

    public function test_a_guest_submission_is_stored_and_confirmed_by_email(): void
    {
        Mail::fake();
        $this->agency();

        $response = $this->submit();

        $visaRequest = VisaRequest::sole();
        $this->assertSame('DE', $visaRequest->destination_country);
        $this->assertSame(2, $visaRequest->applicants);
        $this->assertSame('ani@example.com', $visaRequest->guest_email);
        $this->assertTrue($visaRequest->is_open);

        $response->assertRedirect($visaRequest->signedResultsUrl());
        Mail::assertQueued(VisaRequestSubmitted::class);
    }

    public function test_the_fan_out_job_asks_every_reachable_visa_agency(): void
    {
        Mail::fake();
        $reachable = $this->agency('Reachable Agency', withUser: true);
        $this->agency('Telegram Agency');

        Organization::factory()->create(['name' => 'A Travel Agency', 'type' => 'tourism', 'is_active' => true, 'telegram_chat_id' => '999']);
        Organization::factory()->create(['name' => 'Dormant Visa', 'type' => 'visa', 'is_active' => false, 'telegram_chat_id' => '888']);

        $this->submit();

        $this->assertSame(2, VisaResponse::count());
        $this->assertEqualsCanonicalizing(
            ['Reachable Agency', 'Telegram Agency'],
            VisaResponse::with('organization')->get()->pluck('organization.name')->all(),
        );

        // Only the agency with a dashboard user has an address to write to.
        Mail::assertQueued(VisaRequestReceived::class, 1);
        $this->assertSame(VisaResponse::STATUS_PENDING, VisaResponse::first()->status);
    }

    public function test_a_request_is_refused_when_no_agency_is_taking_them(): void
    {
        $this->submit()->assertSessionHasErrors('destination_country');

        $this->assertSame(0, VisaRequest::count());
    }

    public function test_the_honeypot_discards_a_bot_without_storing_anything(): void
    {
        $this->agency();

        $this->submit(['company' => 'Acme'])->assertRedirect(route('visa.request', ['locale' => 'en']));

        $this->assertSame(0, VisaRequest::count());
    }

    public function test_consent_and_contact_details_are_required(): void
    {
        $this->agency();

        $this->submit(['consent' => null, 'guest_email' => null])
            ->assertSessionHasErrors(['consent', 'guest_email']);

        $this->assertSame(0, VisaRequest::count());
    }

    public function test_the_results_page_needs_the_signed_link_or_the_owner(): void
    {
        $this->agency();
        $this->submit();
        $visaRequest = VisaRequest::sole();

        $this->get(route('visa.show', ['locale' => 'en', 'visaRequest' => $visaRequest->id]))->assertForbidden();
        $this->get($visaRequest->signedResultsUrl())->assertOk();
    }

    public function test_an_agency_answers_through_its_token_link_and_the_traveller_sees_it(): void
    {
        Mail::fake();
        $this->agency();
        $this->submit();

        $visaRequest = VisaRequest::sole();
        $response = $this->pendingResponse($visaRequest, Organization::first());

        $this->get(route('visa.respond', ['locale' => 'en', 'token' => $response->response_token]))
            ->assertOk()
            ->assertSee(__('visa.respond.heading'));

        $this->post(route('visa.respond.store', ['locale' => 'en', 'token' => $response->response_token]), [
            'price_amount' => '45000',
            'price_currency' => 'AMD',
            'processing_days' => 10,
            'reply_text' => 'We handle the appointment and the paperwork.',
        ])->assertRedirect();

        $response->refresh();
        $this->assertSame(VisaResponse::STATUS_RESPONDED, $response->status);
        $this->assertSame('45000.00', $response->price_amount);
        $this->assertNotNull($response->responded_at);
        $this->assertTrue($visaRequest->fresh()->status->isOpen());

        Mail::assertQueued(VisaOfferReceived::class);

        $this->get($visaRequest->signedResultsUrl())
            ->assertOk()
            ->assertSee('45,000.00')
            ->assertSee('We handle the appointment and the paperwork.');
    }

    public function test_a_revised_answer_keeps_the_first_reply_time_and_sends_no_second_email(): void
    {
        Mail::fake();
        $this->agency();
        $this->submit();

        $response = $this->pendingResponse(VisaRequest::sole(), Organization::first());
        $url = route('visa.respond.store', ['locale' => 'en', 'token' => $response->response_token]);

        $this->post($url, ['price_amount' => '45000', 'price_currency' => 'AMD']);
        $firstRepliedAt = $response->fresh()->responded_at;

        $this->post($url, ['price_amount' => '40000', 'price_currency' => 'AMD']);

        $response->refresh();
        $this->assertSame('40000.00', $response->price_amount);
        $this->assertEquals($firstRepliedAt, $response->responded_at);
        Mail::assertQueued(VisaOfferReceived::class, 1);
    }

    public function test_an_agency_can_decline_and_then_no_longer_answers(): void
    {
        $this->agency();
        $this->submit();

        $response = $this->pendingResponse(VisaRequest::sole(), Organization::first());
        $url = route('visa.respond.store', ['locale' => 'en', 'token' => $response->response_token]);

        $this->post($url, ['decline' => '1']);
        $this->assertSame(VisaResponse::STATUS_DECLINED, $response->fresh()->status);

        $this->post($url, ['price_amount' => '45000', 'price_currency' => 'AMD']);
        $this->assertNull($response->fresh()->price_amount);
    }

    public function test_a_closed_request_can_no_longer_be_answered(): void
    {
        $this->agency();
        $this->submit();

        $visaRequest = VisaRequest::sole();
        $response = $this->pendingResponse($visaRequest, Organization::first());
        $visaRequest->close();

        $this->post(route('visa.respond.store', ['locale' => 'en', 'token' => $response->response_token]), [
            'price_amount' => '45000',
            'price_currency' => 'AMD',
        ])->assertRedirect();

        $this->assertSame(VisaResponse::STATUS_PENDING, $response->fresh()->status);
    }

    public function test_the_same_trip_asked_twice_reuses_the_open_request(): void
    {
        $this->agency();

        $this->submit();
        $visaRequest = VisaRequest::sole();

        $this->submit()->assertRedirect(route('visa.show', ['locale' => 'en', 'visaRequest' => $visaRequest->id]));

        $this->assertSame(1, VisaRequest::count());
    }

    public function test_the_owner_can_close_the_request(): void
    {
        $this->agency();
        $this->submit();

        $visaRequest = VisaRequest::sole();

        $this->post($visaRequest->signedUrlFor('visa.close'))->assertRedirect();

        $this->assertFalse($visaRequest->fresh()->is_open);
    }

    public function test_the_whole_section_disappears_when_the_feature_is_off(): void
    {
        config()->set('app.url', config('app.url'));
        FeatureToggle::updateOrCreate(['key' => 'visa'], ['is_enabled' => false]);
        Cache::forget('feature-toggles.states');

        $this->get(route('visa.request', ['locale' => 'en']))->assertNotFound();
    }
}
