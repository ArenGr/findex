<?php

namespace Tests\Feature;

use App\Mail\AutoInsuranceQuoteInterest;
use App\Models\AutoInsuranceQuote;
use App\Models\AutoInsuranceRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Covers the "I'm interested" action on a quote (see
 * AutoInsuranceController::markInterested) - unlike tourism's promo-code
 * claim, this needs no login (no identity to protect, just a signal), so
 * both guests and logged-in customers can use it the same way.
 */
class AutoInsuranceQuoteInterestTest extends TestCase
{
    use RefreshDatabase;

    private function quoteWithRequest(array $organizationOverrides = []): AutoInsuranceQuote
    {
        $organization = Organization::create(array_merge([
            'name' => 'Interest Test Insurer', 'slug' => 'interest-test-insurer-' . uniqid(), 'type' => 'insurance',
            'country_code' => 'AM', 'is_active' => true,
        ], $organizationOverrides));

        $autoInsuranceRequest = AutoInsuranceRequest::create([
            'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'locale' => 'en',
            'vehicle_plate' => '01AA123', 'owner_type' => 'individual', 'owner_id_number' => 'AN1234567',
            'contract_term_months' => 12, 'engine_power_hp' => 100, 'driver_experience_years' => 8, 'accident_free_years' => 2,
        ]);

        return $autoInsuranceRequest->quotes()->create([
            'organization_id' => $organization->id,
            'status' => AutoInsuranceQuote::STATUS_QUOTED,
            'premium_amount' => 30000,
            'premium_currency' => 'AMD',
            'policy_term_months' => 12,
            'responded_at' => now(),
        ]);
    }

    private function interestUrl(AutoInsuranceQuote $quote): string
    {
        return URL::signedRoute('insurance.auto.quotes.interested', [
            'locale' => 'en',
            'autoInsuranceRequest' => $quote->auto_insurance_request_id,
            'quote' => $quote->id,
        ]);
    }

    public function test_a_guest_can_mark_interest_and_the_insurer_gets_emailed(): void
    {
        Mail::fake();
        $quote = $this->quoteWithRequest();
        $orgUser = User::factory()->organization($quote->organization)->create(['email' => 'insurer@example.com']);

        $response = $this->post($this->interestUrl($quote));
        $response->assertRedirect($quote->autoInsuranceRequest->signedResultsUrl());

        // The redirect target itself must actually be reachable by a guest -
        // a plain route() redirect here would 403 (see
        // AutoInsuranceController::markInterested), so this is the real
        // regression guard, not just the redirect target string.
        $this->get($response->headers->get('Location'))->assertOk();

        $this->assertTrue($quote->fresh()->is_interested);
        Mail::assertSent(AutoInsuranceQuoteInterest::class, fn ($mail) => $mail->hasTo('insurer@example.com') && $mail->quote->is($quote));
    }

    public function test_marking_interest_twice_only_emails_once(): void
    {
        Mail::fake();
        $quote = $this->quoteWithRequest();
        User::factory()->organization($quote->organization)->create();

        $this->post($this->interestUrl($quote));
        $this->post($this->interestUrl($quote));

        Mail::assertSent(AutoInsuranceQuoteInterest::class, 1);
    }

    public function test_an_unsigned_request_is_rejected(): void
    {
        $quote = $this->quoteWithRequest();

        $this->post(route('insurance.auto.quotes.interested', [
            'locale' => 'en', 'autoInsuranceRequest' => $quote->auto_insurance_request_id, 'quote' => $quote->id,
        ]))->assertForbidden();

        $this->assertFalse($quote->fresh()->is_interested);
    }

    public function test_a_declined_quote_cannot_be_marked_interested(): void
    {
        $quote = $this->quoteWithRequest();
        $quote->update(['status' => AutoInsuranceQuote::STATUS_DECLINED]);

        $this->post($this->interestUrl($quote))->assertNotFound();
    }

    public function test_results_page_shows_the_insurers_contact_info(): void
    {
        $quote = $this->quoteWithRequest([
            'contact_phone' => '+37499123456',
            'contact_telegram' => 'test_insurer',
        ]);

        $response = $this->get($quote->autoInsuranceRequest->signedResultsUrl());

        $response->assertOk();
        $response->assertSee('tel:+37499123456', false);
        $response->assertSee('https://t.me/test_insurer', false);
    }
}
