<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Models\User;
use App\Services\Notifications\PartnerNotifierInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Claiming a promo code requires being logged in and holding the same
 * signed link the results page itself is gated behind (see
 * QuoteRequestController::claimSuggestion) - so an org can trust that
 * whoever the claim names is really the customer who filed the request.
 */
class QuoteSuggestionClaimTest extends TestCase
{
    use RefreshDatabase;

    private function respondedRequestWithPromo(array $suggestionOverrides = []): QuoteRequest
    {
        $organization = Organization::create([
            'name' => 'Promo Agency', 'slug' => 'promo-agency-' . uniqid(), 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true, 'telegram_chat_id' => '555',
        ]);

        $quoteRequest = QuoteRequest::create([
            'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'locale' => 'en',
            'destination_country' => 'GE', 'check_in' => now()->addDays(10), 'check_out' => now()->addDays(17),
            'adults' => 2, 'children' => 0, 'all_inclusive' => false, 'insurance' => false,
            'expires_at' => now()->addDays(14),
        ]);

        $response = QuoteResponse::create([
            'quote_request_id' => $quoteRequest->id,
            'organization_id' => $organization->id,
            'response_token' => Str::random(40),
            'status' => QuoteResponse::STATUS_RESPONDED,
            'responded_at' => now(),
        ]);

        $response->suggestions()->create(array_merge([
            'price_amount' => 500,
            'price_currency' => 'AMD',
            'promo_code' => 'SUMMER10',
            'promo_note' => '10% off if booked in person',
        ], $suggestionOverrides));

        return $quoteRequest;
    }

    private function claimUrl(QuoteRequest $quoteRequest, int $suggestionId): string
    {
        return URL::signedRoute('tourism.suggestions.claim', [
            'locale' => 'en',
            'quoteRequest' => $quoteRequest->id,
            'suggestion' => $suggestionId,
        ], $quoteRequest->expires_at);
    }

    public function test_a_logged_in_customer_can_claim_a_promo_code_via_the_signed_link(): void
    {
        $this->mock(PartnerNotifierInterface::class)
            ->shouldReceive('notifyClaim')->once()
            ->with(\Mockery::on(fn ($s) => $s->promo_code === 'SUMMER10'))
            ->andReturn(true);

        $quoteRequest = $this->respondedRequestWithPromo();
        $suggestion = $quoteRequest->responses->first()->suggestions->first();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post($this->claimUrl($quoteRequest, $suggestion->id))
            ->assertRedirect(route('tourism.show', $quoteRequest));

        $suggestion->refresh();
        $this->assertTrue($suggestion->is_claimed);
        $this->assertSame($user->id, $suggestion->claimed_by_user_id);
        $this->assertNotNull($suggestion->claimed_at);
    }

    public function test_claiming_without_a_valid_signature_is_rejected(): void
    {
        $quoteRequest = $this->respondedRequestWithPromo();
        $suggestion = $quoteRequest->responses->first()->suggestions->first();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('tourism.suggestions.claim', [
                'locale' => 'en', 'quoteRequest' => $quoteRequest->id, 'suggestion' => $suggestion->id,
            ]))
            ->assertForbidden();

        $this->assertFalse($suggestion->fresh()->is_claimed);
    }

    public function test_claiming_while_logged_out_redirects_to_login(): void
    {
        $quoteRequest = $this->respondedRequestWithPromo();
        $suggestion = $quoteRequest->responses->first()->suggestions->first();

        $this->post($this->claimUrl($quoteRequest, $suggestion->id))
            ->assertRedirect(route('login', ['locale' => 'en']));

        $this->assertFalse($suggestion->fresh()->is_claimed);
    }

    public function test_claiming_an_already_claimed_suggestion_is_a_no_op_and_does_not_renotify(): void
    {
        $this->mock(PartnerNotifierInterface::class)->shouldReceive('notifyClaim')->once()->andReturn(true);

        $quoteRequest = $this->respondedRequestWithPromo();
        $suggestion = $quoteRequest->responses->first()->suggestions->first();
        $firstClaimant = User::factory()->create();
        $secondUser = User::factory()->create();

        $this->actingAs($firstClaimant)->post($this->claimUrl($quoteRequest, $suggestion->id));

        $this->actingAs($secondUser)
            ->post($this->claimUrl($quoteRequest, $suggestion->id))
            ->assertRedirect(route('tourism.show', $quoteRequest));

        $this->assertSame($firstClaimant->id, $suggestion->fresh()->claimed_by_user_id);
    }

    public function test_a_suggestion_without_a_promo_code_cannot_be_claimed(): void
    {
        $quoteRequest = $this->respondedRequestWithPromo(['promo_code' => null]);
        $suggestion = $quoteRequest->responses->first()->suggestions->first();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post($this->claimUrl($quoteRequest, $suggestion->id))
            ->assertNotFound();
    }
}
