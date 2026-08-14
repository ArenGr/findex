<?php

namespace Tests\Feature;

use App\Enums\RateType;
use App\Mail\ExchangeQuoteRequestSubmitted;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\ExchangeQuoteRequest;
use App\Models\ExchangeQuoteResponse;
use App\Models\Organization;
use App\Models\User;
use App\Services\Telegram\TelegramClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ExchangeQuoteSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function usd(): Currency
    {
        return Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1, 'is_active' => true]);
    }

    /**
     * An active, Telegram-connected exchange office publishing a CASH rate
     * for USD - the baseline "matches" case every test builds on or
     * deviates from.
     */
    private function exchangePartner(array $overrides = [], ?string $branchCity = null): Organization
    {
        $organization = Organization::create(array_merge([
            'name' => 'Test Exchange',
            'slug' => 'test-exchange-'.uniqid(),
            'type' => 'exchange',
            'country_code' => 'AM',
            'is_active' => true,
            'telegram_chat_id' => '123456',
        ], $overrides));

        CurrencyRate::create([
            'organization_id' => $organization->id,
            'currency_id' => $this->usd()->id,
            'rate_type' => RateType::CASH,
            'buy_rate' => '384.5000',
            'sell_rate' => '388.5000',
            'scraped_at' => now(),
        ]);

        if ($branchCity) {
            Branch::create([
                'organization_id' => $organization->id,
                'name' => 'Test Branch',
                'city' => $branchCity,
                'is_active' => true,
            ]);
        }

        User::factory()->organization($organization)->create();

        return $organization;
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'currency_code' => 'USD',
            'amount' => '1000',
            'rate_field' => 'buy_rate',
            'notes' => 'Cash in hand, available today.',
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'consent' => '1',
        ], $overrides);
    }

    /**
     * The page has always shown rates and left the visitor to work out that
     * 386.20 against 385.00 is 6,000 dram on their amount. That subtraction is
     * the whole point of the feature, so the page does it.
     */
    public function test_each_offer_is_stated_in_money_not_only_as_a_rate(): void
    {
        $partner = $this->exchangePartner();
        $usd = $this->usd();

        // A second office posting the best public rate to measure against.
        $public = Organization::create([
            'name' => 'Public best', 'slug' => 'public-best-'.uniqid(), 'type' => 'exchange',
            'country_code' => 'AM', 'is_active' => true, 'telegram_chat_id' => '999',
        ]);
        CurrencyRate::create([
            'organization_id' => $public->id, 'currency_id' => $usd->id, 'rate_type' => RateType::CASH,
            'buy_rate' => '385.0000', 'sell_rate' => '389.0000', 'scraped_at' => now(),
        ]);

        $exchangeRequest = ExchangeQuoteRequest::create([
            'currency_id' => $usd->id, 'amount' => 5000, 'rate_field' => 'buy_rate',
            'guest_name' => 'A', 'guest_email' => 'a@example.com', 'locale' => 'en',
            'expires_at' => now()->addDay(),
        ]);

        $exchangeRequest->responses()->create([
            'organization_id' => $partner->id,
            'response_token' => 'tok'.uniqid(),
            'status' => 'responded',
            'posted_rate' => '384.5000',
            'offered_rate' => '386.2000',
            'responded_at' => now(),
        ]);

        $response = $this->get(URL::signedRoute('exchange.show', [
            'locale' => 'en', 'exchangeQuoteRequest' => $exchangeRequest->id,
        ]))->assertOk();

        // 5,000 x 386.20 = 1,931,000 dram, against 5,000 x the best public
        // 385.00 = 1,925,000 - so asking was worth 6,000.
        $response->assertSee('1,931,000')
            ->assertSee('+6,000')
            ->assertSee('Findex got you')
            ->assertSee('You receive')
            // Measured against the open market, not against what this office
            // happened to be posting when the request went out.
            ->assertSee('Best public rate now')
            ->assertSee('385.00');
    }

    /** Nobody beat the open market, so no claim is made about it. */
    public function test_no_saving_is_claimed_when_the_offer_does_not_beat_the_market(): void
    {
        $partner = $this->exchangePartner();
        $usd = $this->usd();

        $exchangeRequest = ExchangeQuoteRequest::create([
            'currency_id' => $usd->id, 'amount' => 5000, 'rate_field' => 'buy_rate',
            'guest_name' => 'A', 'guest_email' => 'a@example.com', 'locale' => 'en',
            'expires_at' => now()->addDay(),
        ]);

        // The partner's own posted rate is 384.50 and it offers exactly that.
        $exchangeRequest->responses()->create([
            'organization_id' => $partner->id,
            'response_token' => 'tok'.uniqid(),
            'status' => 'responded',
            'posted_rate' => '384.5000',
            'offered_rate' => '384.5000',
            'responded_at' => now(),
        ]);

        $this->get(URL::signedRoute('exchange.show', [
            'locale' => 'en', 'exchangeQuoteRequest' => $exchangeRequest->id,
        ]))->assertOk()->assertDontSee('Findex got you');
    }

    /**
     * Picking an offer tells the exchange office nothing about the visitor. It
     * produces a code - FX-48372-A - which the office looks up against the
     * request it already answered. That is the entire handshake.
     */
    public function test_accepting_an_offer_yields_a_code_and_no_personal_data(): void
    {
        [$exchangeRequest, $response] = $this->requestWithOffer();

        $this->assertMatchesRegularExpression('/^FX-\d{5}$/', $exchangeRequest->public_code);

        $this->post(URL::signedRoute('exchange.offers.accept', [
            'locale' => 'en',
            'exchangeQuoteRequest' => $exchangeRequest->id,
            'response' => $response->id,
        ]))->assertRedirect();

        $response->refresh();

        $this->assertSame('accepted', $response->status);
        $this->assertNotNull($response->accepted_at);
        $this->assertSame($exchangeRequest->public_code.'-A', $response->redemption_code);

        $page = $this->get(URL::signedRoute('exchange.show', [
            'locale' => 'en', 'exchangeQuoteRequest' => $exchangeRequest->id,
        ]))->assertOk();

        $page->assertSee($response->redemption_code)
            ->assertSee('Show this code at the exchange office.')
            // The code stands in for the person - none of this may appear.
            ->assertDontSee($exchangeRequest->guest_name)
            ->assertDontSee($exchangeRequest->guest_email);
    }

    /** Changing your mind is allowed while the request is open. */
    public function test_choosing_a_second_offer_releases_the_first(): void
    {
        [$exchangeRequest, $first] = $this->requestWithOffer();

        $second = $exchangeRequest->responses()->create([
            'organization_id' => $this->exchangePartner()->id,
            'response_token' => 'tok'.uniqid(),
            'status' => 'responded',
            'offer_letter' => 'B',
            'posted_rate' => '384.5000',
            'offered_rate' => '385.0000',
            'responded_at' => now(),
        ]);

        foreach ([$first, $second] as $choice) {
            $this->post(URL::signedRoute('exchange.offers.accept', [
                'locale' => 'en', 'exchangeQuoteRequest' => $exchangeRequest->id, 'response' => $choice->id,
            ]))->assertRedirect();
        }

        $this->assertSame('responded', $first->refresh()->status);
        $this->assertSame('accepted', $second->refresh()->status);
        $this->assertNull($first->accepted_at);
    }

    /**
     * A closed request cannot be acted on - the office is no longer holding
     * that rate, and letting someone walk to a counter on a dead code is the
     * worst outcome this feature has.
     */
    public function test_a_closed_request_cannot_have_an_offer_accepted(): void
    {
        [$exchangeRequest, $response] = $this->requestWithOffer();
        $exchangeRequest->forceFill(['expires_at' => now()->subDay()])->save();

        $this->post(URL::signedRoute('exchange.offers.accept', [
            'locale' => 'en', 'exchangeQuoteRequest' => $exchangeRequest->id, 'response' => $response->id,
        ]))->assertStatus(410);

        $this->assertSame('responded', $response->refresh()->status);
    }

    /** Without the signature there is nothing authorizing the change. */
    public function test_accepting_requires_the_same_authorization_as_viewing(): void
    {
        [$exchangeRequest, $response] = $this->requestWithOffer();

        $this->post(route('exchange.offers.accept', [
            'locale' => 'en', 'exchangeQuoteRequest' => $exchangeRequest->id, 'response' => $response->id,
        ]))->assertStatus(403);

        $this->assertSame('responded', $response->refresh()->status);
    }

    /** @return array{0: ExchangeQuoteRequest, 1: ExchangeQuoteResponse} */
    private function requestWithOffer(): array
    {
        $partner = $this->exchangePartner();

        $exchangeRequest = ExchangeQuoteRequest::create([
            'currency_id' => $this->usd()->id, 'amount' => 5000, 'rate_field' => 'buy_rate',
            'guest_name' => 'Zorayr', 'guest_email' => 'zorayr@example.com', 'locale' => 'en',
            'expires_at' => now()->addDay(),
        ]);

        $response = $exchangeRequest->responses()->create([
            'organization_id' => $partner->id,
            'response_token' => 'tok'.uniqid(),
            'status' => 'responded',
            'offer_letter' => 'A',
            'posted_rate' => '384.5000',
            'offered_rate' => '386.2000',
            'responded_at' => now(),
        ]);

        return [$exchangeRequest, $response];
    }

    /**
     * The other half of the handoff: what /rates sends, this form must read.
     * A prefill that silently ignores half the query string is worse than no
     * prefill, because the visitor cannot tell which fields carried over.
     */
    public function test_the_form_prefills_from_the_rates_page_context(): void
    {
        $this->exchangePartner([], 'Yerevan');

        $html = $this->get('/en/exchange?currency=USD&amount=5000&city=Yerevan&rate_field=sell_rate')
            ->assertOk()
            ->assertViewHas('prefilledDirection', 'sell_rate')
            ->getContent();

        $this->assertStringContainsString('value="5000"', $html);
        $this->assertStringContainsString('value="Yerevan" selected', $html);
        // Checked server-side as well as by Alpine, so the prefill survives
        // with JavaScript off.
        $this->assertMatchesRegularExpression('/value="sell_rate"[^>]*checked/', $html);
    }

    /** An unknown direction is ignored rather than landing in the form. */
    public function test_an_unknown_direction_falls_back_instead_of_being_trusted(): void
    {
        $this->exchangePartner();

        $this->get('/en/exchange?currency=USD&rate_field=javascript:alert(1)')
            ->assertOk()
            ->assertViewHas('prefilledDirection', 'buy_rate');
    }

    public function test_guest_can_submit_and_is_emailed_a_signed_results_link(): void
    {
        Mail::fake();
        $this->mock(TelegramClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()->andReturn(['ok' => true, 'result' => ['message_id' => 999]]);
        });
        $this->exchangePartner();

        $response = $this->post(route('exchange.request.store', ['locale' => 'en']), $this->validPayload());

        $exchangeQuoteRequest = ExchangeQuoteRequest::sole();
        $response->assertRedirect($exchangeQuoteRequest->signedResultsUrl());
        $this->assertNull($exchangeQuoteRequest->user_id);
        $this->assertSame('Test Guest', $exchangeQuoteRequest->guest_name);
        $this->assertSame('1000.00', $exchangeQuoteRequest->amount);
        $this->assertSame(1, $exchangeQuoteRequest->responses()->count());
        // The posted_rate snapshot must match the org's buy_rate (this
        // request is rate_field=buy_rate) at submission time.
        $this->assertSame('384.5000', $exchangeQuoteRequest->responses->first()->posted_rate);

        Mail::assertQueued(ExchangeQuoteRequestSubmitted::class, function ($mail) use ($exchangeQuoteRequest) {
            return $mail->exchangeQuoteRequest->is($exchangeQuoteRequest) && $mail->hasTo('guest@example.com');
        });
    }

    public function test_bank_offering_the_same_currency_is_not_matched(): void
    {
        $this->mock(TelegramClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->never();
        });
        // A bank publishes the same currency but isn't an 'exchange' -
        // Organization::exchangePartnersForCurrency is deliberately
        // exchange-only (banks don't negotiate walk-in cash exchanges).
        $this->exchangePartner(['type' => 'bank', 'slug' => 'test-bank-'.uniqid()]);

        $response = $this->post(route('exchange.request.store', ['locale' => 'en']), $this->validPayload());

        $response->assertSessionHasErrors('currency_code');
        $this->assertSame(0, ExchangeQuoteRequest::count());
    }

    public function test_exchange_office_without_telegram_connected_is_not_matched(): void
    {
        $this->exchangePartner(['telegram_chat_id' => null]);

        $response = $this->post(route('exchange.request.store', ['locale' => 'en']), $this->validPayload());

        $response->assertSessionHasErrors('currency_code');
        $this->assertSame(0, ExchangeQuoteRequest::count());
    }

    public function test_amount_below_the_configured_minimum_is_rejected(): void
    {
        $this->exchangePartner();

        $response = $this->post(route('exchange.request.store', ['locale' => 'en']), $this->validPayload(['amount' => '500']));

        $response->assertSessionHasErrors('amount');
        $this->assertSame(0, ExchangeQuoteRequest::count());
    }

    public function test_submission_fails_when_no_partner_offers_the_currency(): void
    {
        // The currency exists (so store() gets past its firstOrFail lookup)
        // but no organization publishes a rate for it - the actual "no
        // partner" case, distinct from "unrecognized currency code".
        $this->usd();

        $response = $this->post(route('exchange.request.store', ['locale' => 'en']), $this->validPayload());

        $response->assertSessionHasErrors('currency_code');
        $this->assertSame(0, ExchangeQuoteRequest::count());
    }

    public function test_honeypot_field_silently_discards_the_submission(): void
    {
        $this->exchangePartner();

        $response = $this->post(route('exchange.request.store', ['locale' => 'en']), array_merge(
            $this->validPayload(),
            ['company' => 'Acme Corp']
        ));

        $response->assertRedirect(route('exchange.request', ['locale' => 'en']));
        $this->assertSame(0, ExchangeQuoteRequest::count());
    }

    public function test_results_page_rejects_access_without_valid_signature_or_ownership(): void
    {
        $this->mock(TelegramClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->andReturn(['ok' => true]);
        });
        $this->exchangePartner();
        $this->post(route('exchange.request.store', ['locale' => 'en']), $this->validPayload());
        $exchangeQuoteRequest = ExchangeQuoteRequest::sole();

        $this->get(route('exchange.show', ['locale' => 'en', 'exchangeQuoteRequest' => $exchangeQuoteRequest]))
            ->assertForbidden();
    }

    public function test_results_page_is_reachable_via_its_signed_link(): void
    {
        $this->mock(TelegramClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->andReturn(['ok' => true]);
        });
        $this->exchangePartner();
        $this->post(route('exchange.request.store', ['locale' => 'en']), $this->validPayload());
        $exchangeQuoteRequest = ExchangeQuoteRequest::sole();

        $this->get($exchangeQuoteRequest->signedResultsUrl())->assertOk();
    }

    public function test_exchange_quote_requests_are_rate_limited_per_ip(): void
    {
        $this->mock(TelegramClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->andReturn(['ok' => true]);
        });
        $this->exchangePartner();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('exchange.request.store', ['locale' => 'en']), $this->validPayload([
                'guest_email' => "guest{$i}@example.com",
            ]))->assertStatus(302);
        }

        $this->post(route('exchange.request.store', ['locale' => 'en']), $this->validPayload([
            'guest_email' => 'guest-throttled@example.com',
        ]))->assertStatus(429);

        $this->assertSame(5, ExchangeQuoteRequest::count());
    }

    public function test_preferred_city_only_matches_offices_with_a_branch_there(): void
    {
        $this->mock(TelegramClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()->andReturn(['ok' => true, 'result' => ['message_id' => 1]]);
        });
        $yerevanOffice = $this->exchangePartner(['slug' => 'yerevan-office-'.uniqid()], 'Yerevan');
        $this->exchangePartner(['slug' => 'gyumri-office-'.uniqid()], 'Gyumri');

        $response = $this->post(route('exchange.request.store', ['locale' => 'en']), $this->validPayload([
            'preferred_city' => 'Yerevan',
        ]));

        $exchangeQuoteRequest = ExchangeQuoteRequest::sole();
        $response->assertRedirect($exchangeQuoteRequest->signedResultsUrl());
        $this->assertSame('Yerevan', $exchangeQuoteRequest->preferred_city);
        $this->assertSame(1, $exchangeQuoteRequest->responses()->count());
        $this->assertSame($yerevanOffice->id, $exchangeQuoteRequest->responses->first()->organization_id);
    }

    public function test_preferred_city_with_no_matching_offices_is_rejected_with_a_region_specific_error(): void
    {
        // Gyumri is a valid, selectable region (an exchange office has a
        // branch there) but that office doesn't publish a USD rate -
        // distinct from "unrecognized region", which Rule::in would catch
        // during validation before this is ever reached.
        $this->exchangePartner(['slug' => 'yerevan-office-'.uniqid()], 'Yerevan');
        $gyumriOffice = Organization::create([
            'name' => 'Gyumri Only Exchange',
            'slug' => 'gyumri-only-exchange-'.uniqid(),
            'type' => 'exchange',
            'country_code' => 'AM',
            'is_active' => true,
            'telegram_chat_id' => '654321',
        ]);
        Branch::create([
            'organization_id' => $gyumriOffice->id,
            'name' => 'Gyumri Branch',
            'city' => 'Gyumri',
            'is_active' => true,
        ]);

        $response = $this->post(route('exchange.request.store', ['locale' => 'en']), $this->validPayload([
            'preferred_city' => 'Gyumri',
        ]));

        $response->assertSessionHasErrors('preferred_city');
        $this->assertSame(0, ExchangeQuoteRequest::count());
    }

    public function test_an_unrecognized_preferred_city_is_rejected_by_validation(): void
    {
        $this->exchangePartner(['slug' => 'yerevan-office-'.uniqid()], 'Yerevan');

        $response = $this->post(route('exchange.request.store', ['locale' => 'en']), $this->validPayload([
            'preferred_city' => 'Not A Real City',
        ]));

        $response->assertSessionHasErrors('preferred_city');
        $this->assertSame(0, ExchangeQuoteRequest::count());
    }
}
