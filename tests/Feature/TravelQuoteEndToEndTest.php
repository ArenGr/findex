<?php

namespace Tests\Feature;

use App\Enums\QuoteRequestStatus;
use App\Mail\QuoteResponseReceived;
use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Models\QuoteSuggestion;
use App\Models\User;
use App\Services\Telegram\TelegramClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The whole MVP in one pass: a traveller files one structured request,
 * Findex sends it to the matching agencies, they answer with structured
 * offers, and the traveller compares them and picks one.
 *
 * Findex does not plan or book the trip - so this test also pins down where
 * its involvement ends.
 */
class TravelQuoteEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private function agency(string $name): Organization
    {
        $organization = Organization::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'type' => 'tourism',
            'country_code' => 'AM',
            'is_active' => true,
            'telegram_chat_id' => '1'.crc32($name),
        ]);

        $organization->tourismDestinations()->create(['country_code' => 'GE']);
        User::factory()->organization($organization)->create();

        return $organization;
    }

    public function test_a_traveller_requests_compares_and_chooses_an_offer(): void
    {
        Mail::fake();

        $this->mock(TelegramClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->andReturn(['ok' => true, 'result' => ['message_id' => 1]]);
        });

        $cheapAgency = $this->agency('Cheap Travel');
        $plushAgency = $this->agency('Plush Travel');

        $traveller = User::factory()->create();

        // 1. The traveller files one structured request.
        $this->actingAs($traveller)
            ->post(route('tourism.request.store', ['locale' => 'en']), [
                'departure_location' => 'Yerevan',
                'destination_countries' => ['GE'],
                'check_in' => now()->addDays(30)->toDateString(),
                'check_out' => now()->addDays(37)->toDateString(),
                'adults' => 2,
                'children' => 1,
                'child_ages' => [8],
                'flight_preference' => QuoteRequest::FLIGHT_INCLUDED,
                'hotel_preference' => '4',
                'meal_preference' => QuoteRequest::MEAL_ALL_INCLUSIVE,
                'priorities' => ['lowest_price', 'family_friendly'],
                'budget_min_amd' => 700000,
                'budget_max_amd' => 1000000,
                'budget_currency' => 'AMD',
                'consent' => '1',
            ])
            ->assertRedirect();

        $quoteRequest = QuoteRequest::sole();

        // 2. Findex sent it to both matching agencies.
        $this->assertSame(2, $quoteRequest->responses()->count());
        $this->assertSame(QuoteRequestStatus::SUBMITTED, $quoteRequest->currentStatus());

        // 3. Each agency answers with a structured offer from its dashboard.
        $this->submitOffer($cheapAgency, $quoteRequest, [
            'price_amount' => '750000',
            'offered_hotel_name' => 'Seaside Inn',
            'hotel_stars' => 3,
            'flight_included' => '1',
            'flight_type' => QuoteSuggestion::FLIGHT_ONE_STOP,
            'meal_plan' => QuoteRequest::MEAL_BREAKFAST,
            'transfer_included' => '0',
            'insurance_included' => '0',
        ]);

        $this->submitOffer($plushAgency, $quoteRequest, [
            'price_amount' => '980000',
            'offered_hotel_name' => 'Grand Resort',
            'hotel_stars' => 5,
            'flight_included' => '1',
            'flight_type' => QuoteSuggestion::FLIGHT_DIRECT,
            'meal_plan' => QuoteRequest::MEAL_ALL_INCLUSIVE,
            'transfer_included' => '1',
            'insurance_included' => '1',
        ]);

        // The traveller was told about each new offer, once each.
        Mail::assertQueued(QuoteResponseReceived::class, 2);

        $quoteRequest->refresh();
        $this->assertSame(QuoteRequestStatus::OFFERS_RECEIVED, $quoteRequest->currentStatus());

        // 4. The status page reports what actually happened, not a guess.
        $this->actingAs($traveller)
            ->get(route('tourism.show', ['locale' => 'en', 'quoteRequest' => $quoteRequest->id]))
            ->assertOk()
            ->assertSee(__('tourism.status_page.view_offers', ['count' => 2]));

        // 5. Both offers are on the offers page, with only factual badges.
        $offersPage = $this->actingAs($traveller)
            ->get(route('tourism.offers', ['locale' => 'en', 'quoteRequest' => $quoteRequest->id]));

        $offersPage->assertOk();
        $offersPage->assertSee('Seaside Inn');
        $offersPage->assertSee('Grand Resort');
        $offersPage->assertSee(__('tourism.offers.badge_lowest_price'));
        $offersPage->assertSee(__('tourism.offers.badge_five_star'));

        // 6. They compare side by side.
        $offers = QuoteSuggestion::orderBy('price_amount')->get();

        $comparePage = $this->actingAs($traveller)->get(route('tourism.compare', [
            'locale' => 'en',
            'quoteRequest' => $quoteRequest->id,
            'offers' => $offers->pluck('id')->implode(','),
        ]));

        $comparePage->assertOk();
        $comparePage->assertSee(__('tourism.compare.row_transfer'));
        $comparePage->assertSee(__('tourism.flight_types.direct'));
        $comparePage->assertSee(__('tourism.flight_types.one_stop'));

        // 7. They choose one, and that is where Findex stops.
        $chosen = $offers->last();

        $this->actingAs($traveller)
            ->post(route('tourism.offers.select', [
                'locale' => 'en',
                'quoteRequest' => $quoteRequest->id,
                'suggestion' => $chosen->id,
            ]))
            ->assertRedirect();

        $this->assertTrue($chosen->fresh()->is_selected);

        // Nothing was booked and nothing was paid - choosing an offer
        // records a choice and hands the traveller the agency's contact
        // details, and that is the whole of it.
        $this->assertSame(QuoteRequestStatus::OFFERS_RECEIVED, $quoteRequest->fresh()->currentStatus());
    }

    private function submitOffer(Organization $agency, QuoteRequest $quoteRequest, array $offer): void
    {
        $assignment = QuoteResponse::where('organization_id', $agency->id)
            ->where('quote_request_id', $quoteRequest->id)
            ->sole();

        $user = $agency->users()->sole();

        $this->actingAs($user, 'organization')
            ->post(route('org.dashboard.travel-requests.offer.store', ['locale' => 'en', 'response' => $assignment->id]), [
                'valid_until' => now()->addDays(2)->format('Y-m-d\TH:i'),
                'contact_phone' => '+37499000000',
                'suggestions' => [array_merge(['price_currency' => 'AMD'], $offer)],
            ])
            ->assertRedirect();

        // Back to the traveller's session for the next step.
        auth('organization')->logout();
    }
}
