<?php

namespace Tests\Feature;

use App\Mail\QuoteResponseReceived;
use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Models\QuoteSuggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The agency side: seeing the requests it was sent, answering with a
 * structured offer, revising it, and being kept out of everyone else's.
 */
class AgencyTravelRequestInboxTest extends TestCase
{
    use RefreshDatabase;

    private function agency(string $name = 'Inbox Test Agency'): Organization
    {
        $organization = Organization::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'type' => 'tourism',
            'country_code' => 'AM',
            'is_active' => true,
        ]);

        $organization->tourismDestinations()->create(['country_code' => 'GE']);

        return $organization;
    }

    private function quoteRequest(array $overrides = []): QuoteRequest
    {
        return QuoteRequest::create(array_merge([
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'locale' => 'en',
            'destination_country' => 'GE',
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(17)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'insurance' => false,
            'expires_at' => now()->addDays(14),
        ], $overrides));
    }

    private function assignment(Organization $organization, ?QuoteRequest $quoteRequest = null): QuoteResponse
    {
        return QuoteResponse::create([
            'quote_request_id' => ($quoteRequest ?? $this->quoteRequest())->id,
            'organization_id' => $organization->id,
            'response_token' => Str::random(40),
            'status' => QuoteResponse::STATUS_PENDING,
        ]);
    }

    private function offerPayload(array $overrides = []): array
    {
        return array_merge([
            'valid_until' => now()->addDays(3)->format('Y-m-d\TH:i'),
            'reply_text' => 'Sea view rooms held for you.',
            'contact_phone' => '+37499123456',
            'suggestions' => [[
                'price_amount' => '825000',
                'price_currency' => 'AMD',
                'offered_hotel_name' => 'Atlantica Oasis',
                'hotel_stars' => 4,
                'flight_included' => '1',
                'flight_type' => QuoteSuggestion::FLIGHT_DIRECT,
                'meal_plan' => QuoteRequest::MEAL_ALL_INCLUSIVE,
                'transfer_included' => '1',
                'insurance_included' => '0',
            ]],
        ], $overrides);
    }

    public function test_an_agency_sees_a_request_it_was_sent(): void
    {
        $organization = $this->agency();
        $user = User::factory()->organization($organization)->create();
        $this->assignment($organization);

        $this->actingAs($user, 'organization')
            ->get(route('org.dashboard.travel-requests.index', ['locale' => 'en']))
            ->assertOk()
            ->assertSee(__('tourism.inbox.needs_answer'));
    }

    public function test_an_agency_cannot_open_a_request_sent_to_another_agency(): void
    {
        $mine = $this->agency('Mine');
        $theirs = $this->agency('Theirs');

        $user = User::factory()->organization($mine)->create();
        $notMine = $this->assignment($theirs);

        $this->actingAs($user, 'organization')
            ->get(route('org.dashboard.travel-requests.show', ['locale' => 'en', 'response' => $notMine->id]))
            ->assertNotFound();
    }

    public function test_an_agency_cannot_submit_an_offer_on_another_agencys_request(): void
    {
        $mine = $this->agency('Mine');
        $theirs = $this->agency('Theirs');

        $user = User::factory()->organization($mine)->create();
        $notMine = $this->assignment($theirs);

        $this->actingAs($user, 'organization')
            ->post(route('org.dashboard.travel-requests.offer.store', ['locale' => 'en', 'response' => $notMine->id]), $this->offerPayload())
            ->assertNotFound();

        $this->assertSame(0, QuoteSuggestion::count());
    }

    public function test_opening_a_request_records_that_the_agency_is_reviewing_it(): void
    {
        $organization = $this->agency();
        $user = User::factory()->organization($organization)->create();
        $assignment = $this->assignment($organization);

        $this->assertNull($assignment->viewed_at);

        $this->actingAs($user, 'organization')
            ->get(route('org.dashboard.travel-requests.show', ['locale' => 'en', 'response' => $assignment->id]))
            ->assertOk();

        $assignment->refresh();

        $this->assertNotNull($assignment->viewed_at);
        $this->assertTrue($assignment->is_reviewing);
    }

    /**
     * "First seen" has to stay first seen, or the traveller's status page
     * would report the agency as having only just looked at it.
     */
    public function test_reopening_a_request_does_not_move_the_first_viewed_time(): void
    {
        $organization = $this->agency();
        $user = User::factory()->organization($organization)->create();
        $assignment = $this->assignment($organization);

        $this->travelTo(now()->subHours(5));
        $assignment->markViewed();
        $firstView = $assignment->fresh()->viewed_at;
        $this->travelBack();

        $this->actingAs($user, 'organization')
            ->get(route('org.dashboard.travel-requests.show', ['locale' => 'en', 'response' => $assignment->id]));

        $this->assertTrue($firstView->equalTo($assignment->fresh()->viewed_at));
    }

    public function test_an_agency_can_submit_a_structured_offer(): void
    {
        Mail::fake();

        $organization = $this->agency();
        $user = User::factory()->organization($organization)->create();
        $assignment = $this->assignment($organization);

        $this->actingAs($user, 'organization')
            ->post(route('org.dashboard.travel-requests.offer.store', ['locale' => 'en', 'response' => $assignment->id]), $this->offerPayload())
            ->assertRedirect();

        $offer = QuoteSuggestion::sole();

        $this->assertSame('Atlantica Oasis', $offer->offered_hotel_name);
        $this->assertSame(4, $offer->hotel_stars);
        $this->assertTrue($offer->flight_included);
        $this->assertSame(QuoteSuggestion::FLIGHT_DIRECT, $offer->flight_type);
        $this->assertSame(QuoteRequest::MEAL_ALL_INCLUSIVE, $offer->meal_plan);
        $this->assertTrue($offer->transfer_included);
        $this->assertFalse($offer->insurance_included);

        $assignment->refresh();

        $this->assertTrue($assignment->has_replied);
        $this->assertNotNull($assignment->valid_until);
        $this->assertTrue($assignment->quoteRequest->fresh()->currentStatus()->value === 'offers_received');
    }

    /**
     * A field the agency left alone must come back as "not stated", not as
     * a definite "no" - see TravelOfferSubmission::boolOrNull().
     */
    public function test_an_unanswered_yes_no_field_stays_unstated(): void
    {
        Mail::fake();

        $organization = $this->agency();
        $user = User::factory()->organization($organization)->create();
        $assignment = $this->assignment($organization);

        $payload = $this->offerPayload();
        $payload['suggestions'][0]['transfer_included'] = '';
        $payload['suggestions'][0]['insurance_included'] = '';

        $this->actingAs($user, 'organization')
            ->post(route('org.dashboard.travel-requests.offer.store', ['locale' => 'en', 'response' => $assignment->id]), $payload);

        $offer = QuoteSuggestion::sole();

        $this->assertNull($offer->transfer_included);
        $this->assertNull($offer->insurance_included);
    }

    public function test_an_agency_can_revise_its_offer_in_place(): void
    {
        Mail::fake();

        $organization = $this->agency();
        $user = User::factory()->organization($organization)->create();
        $assignment = $this->assignment($organization);

        $this->actingAs($user, 'organization')
            ->post(route('org.dashboard.travel-requests.offer.store', ['locale' => 'en', 'response' => $assignment->id]), $this->offerPayload());

        $original = QuoteSuggestion::sole();

        $revision = $this->offerPayload();
        $revision['suggestions'][0]['id'] = $original->id;
        $revision['suggestions'][0]['price_amount'] = '790000';

        $this->actingAs($user, 'organization')
            ->post(route('org.dashboard.travel-requests.offer.store', ['locale' => 'en', 'response' => $assignment->id]), $revision);

        // Revised, not duplicated - the traveller must not end up comparing
        // two versions of the same offer.
        $this->assertSame(1, QuoteSuggestion::count());
        $this->assertSame('790000.00', QuoteSuggestion::sole()->price_amount);
    }

    /**
     * A revision isn't a new answer - re-notifying would tell the traveller
     * "you have a new offer" every time a typo was fixed.
     */
    public function test_revising_an_offer_does_not_email_the_traveller_again(): void
    {
        Mail::fake();

        $organization = $this->agency();
        $user = User::factory()->organization($organization)->create();
        $assignment = $this->assignment($organization);

        $this->actingAs($user, 'organization')
            ->post(route('org.dashboard.travel-requests.offer.store', ['locale' => 'en', 'response' => $assignment->id]), $this->offerPayload());

        Mail::assertQueued(QuoteResponseReceived::class, 1);

        $this->actingAs($user, 'organization')
            ->post(route('org.dashboard.travel-requests.offer.store', ['locale' => 'en', 'response' => $assignment->id]), $this->offerPayload());

        Mail::assertQueued(QuoteResponseReceived::class, 1);
    }

    public function test_an_offer_cannot_be_submitted_once_the_request_has_closed(): void
    {
        $organization = $this->agency();
        $user = User::factory()->organization($organization)->create();

        $quoteRequest = $this->quoteRequest();
        $assignment = $this->assignment($organization, $quoteRequest);

        $quoteRequest->close();

        $this->actingAs($user, 'organization')
            ->post(route('org.dashboard.travel-requests.offer.store', ['locale' => 'en', 'response' => $assignment->id]), $this->offerPayload())
            ->assertStatus(410);

        $this->assertSame(0, QuoteSuggestion::count());
    }

    public function test_an_offer_cannot_be_dated_to_expire_in_the_past(): void
    {
        $organization = $this->agency();
        $user = User::factory()->organization($organization)->create();
        $assignment = $this->assignment($organization);

        $this->actingAs($user, 'organization')
            ->post(
                route('org.dashboard.travel-requests.offer.store', ['locale' => 'en', 'response' => $assignment->id]),
                $this->offerPayload(['valid_until' => now()->subDay()->format('Y-m-d\TH:i')])
            )
            ->assertSessionHasErrors('valid_until');

        $this->assertSame(0, QuoteSuggestion::count());
    }

    public function test_an_agency_can_decline_a_request(): void
    {
        $organization = $this->agency();
        $user = User::factory()->organization($organization)->create();
        $assignment = $this->assignment($organization);

        $this->actingAs($user, 'organization')
            ->post(route('org.dashboard.travel-requests.decline', ['locale' => 'en', 'response' => $assignment->id]))
            ->assertRedirect();

        $this->assertTrue($assignment->fresh()->is_declined);
    }

    /**
     * Matching used to require a connected Telegram chat, which would now
     * leave a dashboard-only agency matched to nothing at all.
     */
    public function test_an_agency_without_telegram_is_still_matched(): void
    {
        $organization = $this->agency();
        User::factory()->organization($organization)->create();

        $this->assertTrue(
            Organization::tourismPartnersForDestination('GE', 2, 500000.0)
                ->whereKey($organization->id)
                ->exists()
        );
    }

    public function test_an_agency_reachable_by_neither_channel_is_not_matched(): void
    {
        // No Telegram chat and no dashboard account: nothing we send would
        // ever reach anyone.
        $organization = $this->agency('Unreachable');

        $this->assertFalse(
            Organization::tourismPartnersForDestination('GE', 2, 500000.0)
                ->whereKey($organization->id)
                ->exists()
        );
    }
}
