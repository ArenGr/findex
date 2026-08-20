<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Services\Telegram\TelegramClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * How wide one request may reach, and how long the details it carries are
 * kept afterwards.
 */
class TravelRetentionAndFanOutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->mock(TelegramClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->andReturn(['ok' => true, 'result' => ['message_id' => 1]]);
        });
    }

    private function agencies(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $name = 'Fanout Agency '.$i;

            $organization = Organization::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'type' => 'tourism',
                'country_code' => 'AM',
                'is_active' => true,
                'telegram_chat_id' => (string) (1000 + $i),
            ]);

            $organization->tourismDestinations()->create(['country_code' => 'GE']);
        }
    }

    private function submit(array $overrides = [])
    {
        return $this->post(route('tourism.request.store', ['locale' => 'en']), array_merge([
            'departure_location' => 'Yerevan',
            'destination_countries' => ['GE'],
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(17)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'consent' => '1',
        ], $overrides));
    }

    /* ---------------------------------------------------------------
     * Fan-out cap
     * ------------------------------------------------------------- */

    /**
     * A request naming no destination matches every agency on the platform,
     * so without a ceiling one submission pages the whole market.
     */
    public function test_a_request_reaches_no_more_agencies_than_the_cap(): void
    {
        $this->agencies(QuoteRequest::MAX_PARTNERS_PER_REQUEST + 8);

        $this->submit(['destination_countries' => [], 'open_to_suggestions' => '1'])->assertRedirect();

        $this->assertSame(
            QuoteRequest::MAX_PARTNERS_PER_REQUEST,
            QuoteRequest::sole()->responses()->count(),
        );
    }

    /**
     * The count the traveller is told about has to be the count actually
     * contacted - the two used to be worked out by separate code.
     */
    public function test_the_reported_agency_count_matches_the_responses_created(): void
    {
        $this->agencies(QuoteRequest::MAX_PARTNERS_PER_REQUEST + 5);

        $this->submit()->assertSessionHas('contacted_count', QuoteRequest::MAX_PARTNERS_PER_REQUEST);

        $this->assertSame(
            QuoteRequest::MAX_PARTNERS_PER_REQUEST,
            QuoteRequest::sole()->responses()->count(),
        );
    }

    public function test_a_small_market_is_unaffected_by_the_cap(): void
    {
        $this->agencies(3);

        $this->submit()->assertRedirect();

        $this->assertSame(3, QuoteRequest::sole()->responses()->count());
    }

    /* ---------------------------------------------------------------
     * Retention
     * ------------------------------------------------------------- */

    private function requestWithChildren(array $overrides = []): QuoteRequest
    {
        return QuoteRequest::create(array_merge([
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'locale' => 'en',
            'destination_country' => 'GE',
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(17)->toDateString(),
            'adults' => 2,
            'children' => 2,
            'child_ages' => [4, 11],
            'insurance' => false,
            'expires_at' => now()->addDays(14),
        ], $overrides));
    }

    public function test_children_ages_are_cleared_once_a_request_is_long_expired(): void
    {
        $request = $this->requestWithChildren(['expires_at' => now()->subDays(45)]);

        $this->artisan('tourism:purge-expired-details')->assertSuccessful();

        $request->refresh();

        $this->assertNull($request->child_ages);

        // The party size is still part of the traveller's own record.
        $this->assertSame(2, $request->children);
    }

    public function test_a_recently_expired_request_keeps_its_ages_through_the_grace_period(): void
    {
        $request = $this->requestWithChildren(['expires_at' => now()->subDays(3)]);

        $this->artisan('tourism:purge-expired-details')->assertSuccessful();

        $this->assertSame([4, 11], $request->fresh()->child_ages);
    }

    public function test_an_open_request_keeps_its_ages(): void
    {
        $request = $this->requestWithChildren();

        $this->artisan('tourism:purge-expired-details')->assertSuccessful();

        $this->assertSame([4, 11], $request->fresh()->child_ages);
    }
}
