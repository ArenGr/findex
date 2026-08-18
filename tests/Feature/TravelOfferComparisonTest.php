<?php

namespace Tests\Feature;

use App\Enums\RateType;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Models\QuoteSuggestion;
use App\Models\User;
use App\Services\TravelOfferComparison;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Comparing offers, and the rules about when a comparison is honest enough
 * to show at all - see TravelOfferComparison.
 */
class TravelOfferComparisonTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
    }

    private function quoteRequest(): QuoteRequest
    {
        return QuoteRequest::create([
            'user_id' => $this->owner->id,
            'locale' => 'en',
            'destination_country' => 'GE',
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(17)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'insurance' => false,
            'expires_at' => now()->addDays(14),
        ]);
    }

    private function offer(QuoteRequest $quoteRequest, array $attributes = [], array $responseAttributes = []): QuoteSuggestion
    {
        $name = 'Agency '.Str::random(6);

        $organization = Organization::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'type' => 'tourism',
            'country_code' => 'AM',
            'is_active' => true,
            'telegram_chat_id' => '1',
        ]);

        $response = QuoteResponse::create(array_merge([
            'quote_request_id' => $quoteRequest->id,
            'organization_id' => $organization->id,
            'response_token' => Str::random(40),
            'status' => QuoteResponse::STATUS_RESPONDED,
            'responded_at' => now(),
        ], $responseAttributes));

        return $response->suggestions()->create(array_merge([
            'price_amount' => 500000,
            'price_currency' => 'AMD',
            'offered_hotel_name' => 'Test Hotel',
        ], $attributes));
    }

    private function rowsFor(QuoteRequest $quoteRequest)
    {
        return app(TravelOfferComparison::class)->for(
            $quoteRequest->fresh()->load(['responses.organization', 'responses.suggestions'])
        );
    }

    public function test_the_cheapest_of_several_comparable_offers_is_badged(): void
    {
        $quoteRequest = $this->quoteRequest();
        $cheapest = $this->offer($quoteRequest, ['price_amount' => 400000]);
        $this->offer($quoteRequest, ['price_amount' => 600000]);

        $rows = $this->rowsFor($quoteRequest);

        $this->assertContains('lowest_price', $rows->firstWhere('offer.id', $cheapest->id)['badges']);
        $this->assertSame(1, $rows->filter(fn ($row) => in_array('lowest_price', $row['badges'], true))->count());
    }

    /**
     * One price is not a comparison - there is nothing it was lower than.
     */
    public function test_a_lone_offer_is_not_badged_as_the_lowest_price(): void
    {
        $quoteRequest = $this->quoteRequest();
        $this->offer($quoteRequest, ['price_amount' => 400000]);

        $this->assertSame([], $this->rowsFor($quoteRequest)->first()['badges']);
    }

    /**
     * Picking a winner between two identical prices would be arbitrary.
     */
    public function test_a_tie_for_the_lowest_price_badges_neither(): void
    {
        $quoteRequest = $this->quoteRequest();
        $this->offer($quoteRequest, ['price_amount' => 400000]);
        $this->offer($quoteRequest, ['price_amount' => 400000]);

        $rows = $this->rowsFor($quoteRequest);

        $this->assertSame(0, $rows->filter(fn ($row) => in_array('lowest_price', $row['badges'], true))->count());
    }

    /**
     * The one rule that matters most: with no rate available, "610 USD"
     * must not be ranked against "500,000 AMD" as though the numbers were
     * in the same unit.
     */
    public function test_offers_in_different_currencies_are_not_ranked_without_a_rate(): void
    {
        $quoteRequest = $this->quoteRequest();
        $this->offer($quoteRequest, ['price_amount' => 610, 'price_currency' => 'USD']);
        $this->offer($quoteRequest, ['price_amount' => 500000, 'price_currency' => 'AMD']);

        $rows = $this->rowsFor($quoteRequest);

        $this->assertSame(0, $rows->filter(fn ($row) => in_array('lowest_price', $row['badges'], true))->count());
    }

    public function test_offers_in_different_currencies_are_ranked_when_a_rate_exists(): void
    {
        $quoteRequest = $this->quoteRequest();

        $usd = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1]);

        $organization = Organization::create([
            'name' => 'Rate Source', 'slug' => 'rate-source', 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => true,
        ]);

        CurrencyRate::create([
            'organization_id' => $organization->id,
            'currency_id' => $usd->id,
            'rate_type' => RateType::NON_CASH,
            'buy_rate' => 390,
            'sell_rate' => 400,
            'scraped_at' => now(),
        ]);

        // ~240,000 AMD, genuinely cheaper than the 500,000 AMD offer.
        $cheapInUsd = $this->offer($quoteRequest, ['price_amount' => 610, 'price_currency' => 'USD']);
        $this->offer($quoteRequest, ['price_amount' => 500000, 'price_currency' => 'AMD']);

        $rows = $this->rowsFor($quoteRequest);

        $this->assertContains('lowest_price', $rows->firstWhere('offer.id', $cheapInUsd->id)['badges']);
    }

    public function test_only_factual_badges_are_produced(): void
    {
        $quoteRequest = $this->quoteRequest();

        $offer = $this->offer($quoteRequest, [
            'hotel_stars' => 5,
            'flight_type' => QuoteSuggestion::FLIGHT_DIRECT,
            'meal_plan' => QuoteRequest::MEAL_ALL_INCLUSIVE,
        ]);

        $badges = $this->rowsFor($quoteRequest)->firstWhere('offer.id', $offer->id)['badges'];

        $this->assertContains('five_star', $badges);
        $this->assertContains('direct_flight', $badges);
        $this->assertContains('all_inclusive', $badges);
    }

    public function test_a_four_star_hotel_does_not_get_the_five_star_badge(): void
    {
        $quoteRequest = $this->quoteRequest();
        $offer = $this->offer($quoteRequest, ['hotel_stars' => 4]);

        $this->assertNotContains('five_star', $this->rowsFor($quoteRequest)->firstWhere('offer.id', $offer->id)['badges']);
    }

    public function test_an_unanswered_agency_contributes_no_offers(): void
    {
        $quoteRequest = $this->quoteRequest();
        $this->offer($quoteRequest, [], ['status' => QuoteResponse::STATUS_PENDING, 'responded_at' => null]);

        $this->assertTrue($this->rowsFor($quoteRequest)->isEmpty());
    }

    public function test_the_comparison_page_only_lines_up_offers_from_this_request(): void
    {
        $mine = $this->quoteRequest();
        $theirs = $this->quoteRequest();

        $myOffer = $this->offer($mine);
        $theirOffer = $this->offer($theirs);

        $response = $this->actingAs($this->owner)->get(route('tourism.compare', [
            'locale' => 'en',
            'quoteRequest' => $mine->id,
            'offers' => $myOffer->id.','.$theirOffer->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('selected', fn ($selected) => $selected->count() === 1
            && $selected->first()['offer']->id === $myOffer->id);
    }

    public function test_an_expired_offer_is_still_visible_but_cannot_be_chosen(): void
    {
        $quoteRequest = $this->quoteRequest();
        $offer = $this->offer($quoteRequest, [], ['valid_until' => now()->subHour()]);

        $this->assertTrue($offer->fresh()->is_expired);
        $this->assertFalse($offer->fresh()->is_selectable);

        $this->actingAs($this->owner)
            ->post(route('tourism.offers.select', ['locale' => 'en', 'quoteRequest' => $quoteRequest->id, 'suggestion' => $offer->id]))
            ->assertStatus(410);

        // Still readable - it's the record of what was quoted.
        $this->actingAs($this->owner)
            ->get(route('tourism.offers', ['locale' => 'en', 'quoteRequest' => $quoteRequest->id]))
            ->assertOk()
            ->assertSee(__('tourism.offers.expired_badge'));
    }

    public function test_an_offer_with_no_deadline_never_expires(): void
    {
        $quoteRequest = $this->quoteRequest();
        $offer = $this->offer($quoteRequest);

        $this->assertFalse($offer->fresh()->is_expired);
        $this->assertTrue($offer->fresh()->is_selectable);
    }

    public function test_choosing_an_offer_records_it_and_replaces_any_earlier_choice(): void
    {
        $quoteRequest = $this->quoteRequest();
        $first = $this->offer($quoteRequest);
        $second = $this->offer($quoteRequest);

        $this->actingAs($this->owner)
            ->post(route('tourism.offers.select', ['locale' => 'en', 'quoteRequest' => $quoteRequest->id, 'suggestion' => $first->id]))
            ->assertRedirect();

        $this->assertTrue($first->fresh()->is_selected);

        $this->actingAs($this->owner)
            ->post(route('tourism.offers.select', ['locale' => 'en', 'quoteRequest' => $quoteRequest->id, 'suggestion' => $second->id]))
            ->assertRedirect();

        $this->assertFalse($first->fresh()->is_selected);
        $this->assertTrue($second->fresh()->is_selected);
    }

    public function test_an_offer_cannot_be_chosen_once_the_request_is_closed(): void
    {
        $quoteRequest = $this->quoteRequest();
        $offer = $this->offer($quoteRequest);

        $quoteRequest->close();

        $this->actingAs($this->owner)
            ->post(route('tourism.offers.select', ['locale' => 'en', 'quoteRequest' => $quoteRequest->id, 'suggestion' => $offer->id]))
            ->assertStatus(410);

        $this->assertFalse($offer->fresh()->is_selected);
    }

    public function test_another_users_offer_pages_are_not_reachable(): void
    {
        $quoteRequest = $this->quoteRequest();
        $offer = $this->offer($quoteRequest);
        $stranger = User::factory()->create();

        foreach (['tourism.show', 'tourism.offers', 'tourism.compare'] as $route) {
            $this->actingAs($stranger)
                ->get(route($route, ['locale' => 'en', 'quoteRequest' => $quoteRequest->id]))
                ->assertForbidden();
        }

        $this->actingAs($stranger)
            ->get(route('tourism.offers.show', ['locale' => 'en', 'quoteRequest' => $quoteRequest->id, 'suggestion' => $offer->id]))
            ->assertForbidden();
    }

    public function test_the_offer_detail_page_shows_the_structured_offer_and_the_agency(): void
    {
        $quoteRequest = $this->quoteRequest();

        $offer = $this->offer($quoteRequest, [
            'offered_hotel_name' => 'Atlantica Oasis',
            'hotel_stars' => 5,
            'flight_type' => QuoteSuggestion::FLIGHT_DIRECT,
            'meal_plan' => QuoteRequest::MEAL_ALL_INCLUSIVE,
            'transfer_included' => true,
            'insurance_included' => false,
        ]);

        $response = $this->actingAs($this->owner)->get(route('tourism.offers.show', [
            'locale' => 'en',
            'quoteRequest' => $quoteRequest->id,
            'suggestion' => $offer->id,
        ]));

        $response->assertOk();
        $response->assertSee('Atlantica Oasis');
        $response->assertSee($offer->response->organization->name);
        $response->assertSee(__('tourism.flight_types.direct'));
        $response->assertSee(__('tourism.meals.all_inclusive'));
        $response->assertSee(__('tourism.offer.choose'));

        // A transfer the agency confirmed and an insurance it explicitly
        // excluded must read differently from each other.
        $response->assertSee(__('tourism.offer.included'));
        $response->assertSee(__('tourism.offer.not_included'));

        // No booking or payment anywhere on this page - see the scope note
        // in TravelOfferComparison and the choose action.
        $response->assertDontSee('Proceed to Booking');
    }

    public function test_an_offer_id_from_another_request_is_not_found_on_this_one(): void
    {
        $mine = $this->quoteRequest();
        $theirs = $this->quoteRequest();
        $theirOffer = $this->offer($theirs);

        $this->actingAs($this->owner)
            ->get(route('tourism.offers.show', ['locale' => 'en', 'quoteRequest' => $mine->id, 'suggestion' => $theirOffer->id]))
            ->assertNotFound();
    }

    public function test_a_guest_reaches_the_offers_page_through_its_signed_link(): void
    {
        $quoteRequest = $this->quoteRequest();
        $quoteRequest->update(['user_id' => null, 'guest_email' => 'guest@example.com', 'guest_name' => 'Guest']);
        $this->offer($quoteRequest);

        $this->get($quoteRequest->fresh()->signedOffersUrl())->assertOk();

        // The same URL without its signature is refused.
        $this->get(route('tourism.offers', ['locale' => 'en', 'quoteRequest' => $quoteRequest->id]))->assertForbidden();
    }
}
