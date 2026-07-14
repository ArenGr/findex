<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Services\Notifications\PartnerNotifierInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class RemindPartnersOfPendingQuotesTest extends TestCase
{
    use RefreshDatabase;

    private function pendingResponse(array $overrides = []): QuoteResponse
    {
        $organization = Organization::create([
            'name' => 'Reminder Test Agency', 'slug' => 'reminder-test-agency-' . uniqid(), 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true, 'telegram_chat_id' => '999',
        ]);
        $quoteRequest = QuoteRequest::create(array_merge([
            'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'locale' => 'en',
            'destination_country' => 'GE', 'check_in' => now()->addDays(10), 'check_out' => now()->addDays(17),
            'adults' => 2, 'children' => 0, 'all_inclusive' => false, 'insurance' => false,
            'expires_at' => now()->addDays(5),
        ], $overrides['quoteRequest'] ?? []));

        $response = QuoteResponse::create([
            'quote_request_id' => $quoteRequest->id,
            'organization_id' => $organization->id,
            'response_token' => Str::random(40),
            'status' => QuoteResponse::STATUS_PENDING,
        ]);
        $response->forceFill(['created_at' => $overrides['created_at'] ?? now()->subHours(30)])->save();

        return $response;
    }

    public function test_reminds_a_pending_response_older_than_24_hours(): void
    {
        $response = $this->pendingResponse(['created_at' => now()->subHours(30)]);

        $notifier = $this->mock(PartnerNotifierInterface::class);
        $notifier->shouldReceive('remind')->once()->with(\Mockery::on(fn ($r) => $r->id === $response->id))->andReturn(true);

        Artisan::call('tourism:remind-partners');

        $this->assertNotNull($response->fresh()->reminded_at);
    }

    public function test_does_not_remind_a_response_younger_than_24_hours(): void
    {
        $this->pendingResponse(['created_at' => now()->subHours(5)]);

        $notifier = $this->mock(PartnerNotifierInterface::class);
        $notifier->shouldNotReceive('remind');

        Artisan::call('tourism:remind-partners');
    }

    public function test_does_not_re_remind_an_already_reminded_response(): void
    {
        $response = $this->pendingResponse(['created_at' => now()->subHours(30)]);
        $response->update(['reminded_at' => now()->subHour()]);

        $notifier = $this->mock(PartnerNotifierInterface::class);
        $notifier->shouldNotReceive('remind');

        Artisan::call('tourism:remind-partners');
    }

    public function test_does_not_remind_for_an_expired_request(): void
    {
        $this->pendingResponse([
            'created_at' => now()->subHours(30),
            'quoteRequest' => ['expires_at' => now()->subDay()],
        ]);

        $notifier = $this->mock(PartnerNotifierInterface::class);
        $notifier->shouldNotReceive('remind');

        Artisan::call('tourism:remind-partners');
    }

    public function test_does_not_remind_an_already_responded_response(): void
    {
        $response = $this->pendingResponse(['created_at' => now()->subHours(30)]);
        $response->update(['status' => QuoteResponse::STATUS_RESPONDED, 'responded_at' => now()]);

        $notifier = $this->mock(PartnerNotifierInterface::class);
        $notifier->shouldNotReceive('remind');

        Artisan::call('tourism:remind-partners');
    }

    public function test_marks_reminded_at_even_when_delivery_fails(): void
    {
        $response = $this->pendingResponse(['created_at' => now()->subHours(30)]);

        $notifier = $this->mock(PartnerNotifierInterface::class);
        $notifier->shouldReceive('remind')->once()->andReturn(false);

        Artisan::call('tourism:remind-partners');

        $this->assertNotNull($response->fresh()->reminded_at);
    }
}
