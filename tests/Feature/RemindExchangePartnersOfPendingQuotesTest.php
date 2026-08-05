<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\ExchangeQuoteRequest;
use App\Models\ExchangeQuoteResponse;
use App\Models\Organization;
use App\Services\Notifications\ExchangeNotifierInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class RemindExchangePartnersOfPendingQuotesTest extends TestCase
{
    use RefreshDatabase;

    private function pendingResponse(array $overrides = []): ExchangeQuoteResponse
    {
        $organization = Organization::create([
            'name' => 'Reminder Test Exchange', 'slug' => 'reminder-test-exchange-'.uniqid(), 'type' => 'exchange',
            'country_code' => 'AM', 'is_active' => true, 'telegram_chat_id' => '999',
        ]);
        $usd = Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1, 'is_active' => true]);

        $exchangeQuoteRequest = ExchangeQuoteRequest::create(array_merge([
            'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'locale' => 'en',
            'currency_id' => $usd->id, 'amount' => 1000, 'rate_field' => 'buy_rate',
            'expires_at' => now()->addDays(5),
        ], $overrides['exchangeQuoteRequest'] ?? []));

        $response = ExchangeQuoteResponse::create([
            'exchange_quote_request_id' => $exchangeQuoteRequest->id,
            'organization_id' => $organization->id,
            'response_token' => Str::random(40),
            'status' => ExchangeQuoteResponse::STATUS_PENDING,
            'posted_rate' => '384.5000',
        ]);
        $response->forceFill(['created_at' => $overrides['created_at'] ?? now()->subHours(30)])->save();

        return $response;
    }

    public function test_reminds_a_pending_response_older_than_24_hours(): void
    {
        $response = $this->pendingResponse(['created_at' => now()->subHours(30)]);

        $notifier = $this->mock(ExchangeNotifierInterface::class);
        $notifier->shouldReceive('remind')->once()->with(\Mockery::on(fn ($r) => $r->id === $response->id))->andReturn(true);

        Artisan::call('exchange:remind-partners');

        $this->assertNotNull($response->fresh()->reminded_at);
    }

    public function test_does_not_remind_a_response_younger_than_24_hours(): void
    {
        $this->pendingResponse(['created_at' => now()->subHours(5)]);

        $notifier = $this->mock(ExchangeNotifierInterface::class);
        $notifier->shouldNotReceive('remind');

        Artisan::call('exchange:remind-partners');
    }

    public function test_does_not_re_remind_an_already_reminded_response(): void
    {
        $response = $this->pendingResponse(['created_at' => now()->subHours(30)]);
        $response->update(['reminded_at' => now()->subHour()]);

        $notifier = $this->mock(ExchangeNotifierInterface::class);
        $notifier->shouldNotReceive('remind');

        Artisan::call('exchange:remind-partners');
    }

    public function test_does_not_remind_for_an_expired_request(): void
    {
        $this->pendingResponse([
            'created_at' => now()->subHours(30),
            'exchangeQuoteRequest' => ['expires_at' => now()->subDay()],
        ]);

        $notifier = $this->mock(ExchangeNotifierInterface::class);
        $notifier->shouldNotReceive('remind');

        Artisan::call('exchange:remind-partners');
    }

    public function test_marks_reminded_at_even_when_delivery_fails(): void
    {
        $response = $this->pendingResponse(['created_at' => now()->subHours(30)]);

        $notifier = $this->mock(ExchangeNotifierInterface::class);
        $notifier->shouldReceive('remind')->once()->andReturn(false);

        Artisan::call('exchange:remind-partners');

        $this->assertNotNull($response->fresh()->reminded_at);
    }
}
