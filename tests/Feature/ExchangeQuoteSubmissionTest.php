<?php

namespace Tests\Feature;

use App\Enums\RateType;
use App\Mail\ExchangeQuoteRequestSubmitted;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\ExchangeQuoteRequest;
use App\Models\Organization;
use App\Models\User;
use App\Services\Telegram\TelegramClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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
