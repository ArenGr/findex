<?php

namespace Tests\Feature;

use App\Jobs\SendQuoteRequestToPartnersJob;
use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Services\Telegram\TelegramClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendQuoteRequestToPartnersJobTest extends TestCase
{
    use RefreshDatabase;

    private function quoteRequest(array $overrides = []): QuoteRequest
    {
        return QuoteRequest::create(array_merge([
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'locale' => 'hy',
            'destination_country' => 'GE',
            'hotel_name' => 'Test Hotel',
            'check_in' => now()->addDays(10),
            'check_out' => now()->addDays(17),
            'adults' => 2,
            'children' => 0,
            'all_inclusive' => false,
            'insurance' => false,
            'expires_at' => now()->addDays(14),
        ], $overrides));
    }

    private function partner(string $countryCode, array $overrides = []): Organization
    {
        $organization = Organization::create(array_merge([
            'name' => 'Partner ' . $countryCode,
            'slug' => 'partner-' . strtolower($countryCode) . '-' . uniqid(),
            'type' => 'tourism',
            'country_code' => 'AM',
            'is_active' => true,
            'telegram_chat_id' => '111',
        ], $overrides));

        $organization->tourismDestinations()->create(['country_code' => $countryCode]);

        return $organization;
    }

    public function test_only_active_tourism_partners_serving_the_destination_are_messaged(): void
    {
        $matching = $this->partner('GE');
        $wrongDestination = $this->partner('EG');
        $inactive = $this->partner('GE', ['is_active' => false]);
        $notTourism = Organization::create([
            'name' => 'Bank', 'slug' => 'bank-' . uniqid(), 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => true, 'telegram_chat_id' => '222',
        ]);
        $notConnected = Organization::create([
            'name' => 'Not Connected', 'slug' => 'not-connected-' . uniqid(), 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true,
        ]);
        $notConnected->tourismDestinations()->create(['country_code' => 'GE']);

        $this->mock(TelegramClient::class, function ($mock) use ($matching) {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->with($matching->telegram_chat_id, \Mockery::type('string'))
                ->andReturn(['ok' => true, 'result' => ['message_id' => 777]]);
        });

        $quoteRequest = $this->quoteRequest();
        (new SendQuoteRequestToPartnersJob($quoteRequest))->handle(app(TelegramClient::class));

        $response = QuoteResponse::sole();
        $this->assertSame($matching->id, $response->organization_id);
        $this->assertSame(777, $response->telegram_message_id);
    }

    public function test_failed_telegram_send_does_not_create_a_quote_response(): void
    {
        $this->partner('GE');
        $this->mock(TelegramClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()->andReturn(['ok' => false, 'description' => 'chat not found']);
        });

        $quoteRequest = $this->quoteRequest();
        (new SendQuoteRequestToPartnersJob($quoteRequest))->handle(app(TelegramClient::class));

        $this->assertSame(0, QuoteResponse::count());
    }

    public function test_message_is_written_in_armenian_regardless_of_ambient_locale(): void
    {
        app()->setLocale('en');
        $this->partner('GE');
        $quoteRequest = $this->quoteRequest(['locale' => 'en', 'destination_country' => 'GE']);

        $this->mock(TelegramClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->with(\Mockery::any(), \Mockery::on(fn ($text) => str_contains($text, 'Ուղղություն')))
                ->andReturn(['ok' => true]);
        });

        (new SendQuoteRequestToPartnersJob($quoteRequest))->handle(app(TelegramClient::class));
    }
}
