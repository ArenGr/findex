<?php

namespace Tests\Feature;

use App\Jobs\SendQuoteRequestToPartnersJob;
use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Models\User;
use App\Services\Notifications\PartnerNotifierInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the partner-matching/fan-out logic only. Message content and
 * delivery live in TelegramPartnerNotifier (see TelegramPartnerNotifierTest)
 * behind the PartnerNotifierInterface seam, so this job is tested against
 * that interface and shouldn't care which channel implements it.
 */
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
        User::factory()->organization($organization)->create();

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

        $this->mock(PartnerNotifierInterface::class, function ($mock) use ($matching) {
            $mock->shouldReceive('notify')
                ->once()
                ->with(\Mockery::on(fn (QuoteResponse $response) => $response->organization_id === $matching->id))
                ->andReturn(true);
        });

        $quoteRequest = $this->quoteRequest();
        (new SendQuoteRequestToPartnersJob($quoteRequest))->handle(app(PartnerNotifierInterface::class));

        $response = QuoteResponse::sole();
        $this->assertSame($matching->id, $response->organization_id);
        $this->assertSame(QuoteResponse::STATUS_PENDING, $response->status);
        $this->assertNotEmpty($response->response_token);
    }

    public function test_a_pending_quote_response_is_still_created_when_the_notification_fails(): void
    {
        $this->partner('GE');
        $this->mock(PartnerNotifierInterface::class, function ($mock) {
            $mock->shouldReceive('notify')->once()->andReturn(false);
        });

        $quoteRequest = $this->quoteRequest();
        (new SendQuoteRequestToPartnersJob($quoteRequest))->handle(app(PartnerNotifierInterface::class));

        $response = QuoteResponse::sole();
        $this->assertSame(QuoteResponse::STATUS_PENDING, $response->status);
        $this->assertNotEmpty($response->response_token);
    }

    public function test_every_matched_partner_gets_its_own_response_and_notify_call(): void
    {
        $first = $this->partner('GE');
        $second = $this->partner('GE');

        $this->mock(PartnerNotifierInterface::class, function ($mock) {
            $mock->shouldReceive('notify')->twice()->andReturn(true);
        });

        $quoteRequest = $this->quoteRequest();
        (new SendQuoteRequestToPartnersJob($quoteRequest))->handle(app(PartnerNotifierInterface::class));

        $this->assertSame(2, QuoteResponse::count());
        $this->assertSame(
            [$first->id, $second->id],
            QuoteResponse::orderBy('organization_id')->pluck('organization_id')->sort()->values()->all()
        );
    }
}
