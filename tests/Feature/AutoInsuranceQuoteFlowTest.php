<?php

namespace Tests\Feature;

use App\Models\AutoInsuranceQuote;
use App\Models\AutoInsuranceRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the auto insurance request/results flow end to end: unlike
 * tourism, there's no Telegram/secure-token round trip - quotes are fetched
 * synchronously through InsuranceQuoteProviderInterface (MockInsuranceProvider
 * in this environment) and land on the results page immediately.
 */
class AutoInsuranceQuoteFlowTest extends TestCase
{
    use RefreshDatabase;

    private function insurancePartner(array $overrides = []): Organization
    {
        $unique = uniqid();

        return Organization::create(array_merge([
            'name' => 'Test Insurance Co ' . $unique,
            'slug' => 'test-insurance-co-' . $unique,
            'type' => 'insurance',
            'country_code' => 'AM',
            'is_active' => true,
        ], $overrides));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'vehicle_plate' => '01AA123',
            'owner_id_number' => 'AN1234567',
            'contract_term_months' => 12,
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'consent' => '1',
        ], $overrides);
    }

    public function test_request_form_renders(): void
    {
        $this->get(route('insurance.auto.request', ['locale' => 'en']))
            ->assertOk()
            ->assertSee(__('auto_insurance.request.heading'));
    }

    public function test_submitting_creates_a_request_and_a_quote_per_active_insurance_partner(): void
    {
        $matching1 = $this->insurancePartner();
        $matching2 = $this->insurancePartner();
        $inactive = $this->insurancePartner(['is_active' => false]);
        $wrongType = Organization::create([
            'name' => 'Some Bank', 'slug' => 'some-bank-' . uniqid(), 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => true,
        ]);

        $response = $this->post(route('insurance.auto.request.store', ['locale' => 'en']), $this->validPayload());

        $autoInsuranceRequest = AutoInsuranceRequest::sole();
        $response->assertRedirect($autoInsuranceRequest->signedResultsUrl());

        $this->assertSame(2, AutoInsuranceQuote::count());
        $quotedOrgIds = AutoInsuranceQuote::pluck('organization_id')->sort()->values()->all();
        $this->assertSame([$matching1->id, $matching2->id], $quotedOrgIds);

        AutoInsuranceQuote::all()->each(function (AutoInsuranceQuote $quote) {
            $this->assertSame(AutoInsuranceQuote::STATUS_QUOTED, $quote->status);
            $this->assertNotNull($quote->premium_amount);
            $this->assertSame('AMD', $quote->premium_currency);
            $this->assertNotNull($quote->responded_at);
        });
    }

    public function test_results_page_shows_quotes_sorted_by_price_with_best_price_badge(): void
    {
        // Different ids give MockInsuranceProvider's deterministic per-partner
        // variance different values, guaranteeing distinct premiums to sort.
        $cheap = $this->insurancePartner();
        $expensive = $this->insurancePartner();

        $this->post(route('insurance.auto.request.store', ['locale' => 'en']), $this->validPayload());
        $autoInsuranceRequest = AutoInsuranceRequest::sole();

        $response = $this->get($autoInsuranceRequest->signedResultsUrl());
        $response->assertOk();
        $response->assertSee(__('auto_insurance.results.best_price_badge'));

        $quotes = AutoInsuranceQuote::with('organization')->orderBy('premium_amount')->get();
        $this->assertNotEquals($quotes->first()->premium_amount, $quotes->last()->premium_amount);

        // Each org's name also appears earlier in the page inside the Alpine
        // `comparable` JSON blob (in sorted order too), so search for the
        // LAST occurrence to land on the actual rendered card rather than
        // that blob - guards against a sort that runs without error but
        // orders the visible cards wrong.
        $html = $response->getContent();
        $cheapestPosition = strrpos($html, $quotes->first()->organization->name);
        $pricierPosition = strrpos($html, $quotes->last()->organization->name);
        $this->assertLessThan($pricierPosition, $cheapestPosition);

        // The badge must sit on the actually-cheapest card, not just appear
        // somewhere on the page.
        $badgePosition = strpos($html, __('auto_insurance.results.best_price_badge'));
        $this->assertGreaterThan($cheapestPosition, $badgePosition);
        $this->assertLessThan($pricierPosition, $badgePosition);
    }

    public function test_show_page_requires_ownership_or_a_valid_signature(): void
    {
        $this->insurancePartner();
        $this->post(route('insurance.auto.request.store', ['locale' => 'en']), $this->validPayload());
        $autoInsuranceRequest = AutoInsuranceRequest::sole();

        $this->get(route('insurance.auto.show', ['locale' => 'en', 'autoInsuranceRequest' => $autoInsuranceRequest->id]))
            ->assertForbidden();
    }

    public function test_logged_in_user_is_redirected_straight_to_the_unsigned_show_route(): void
    {
        $this->insurancePartner();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('insurance.auto.request.store', ['locale' => 'en']), $this->validPayload());

        $autoInsuranceRequest = AutoInsuranceRequest::sole();
        $this->assertSame($user->id, $autoInsuranceRequest->user_id);
        $response->assertRedirect(route('insurance.auto.show', ['locale' => 'en', 'autoInsuranceRequest' => $autoInsuranceRequest->id]));

        $this->get(route('insurance.auto.show', ['locale' => 'en', 'autoInsuranceRequest' => $autoInsuranceRequest->id]))
            ->assertOk();
    }

    public function test_submission_requires_vehicle_and_owner_fields(): void
    {
        $this->insurancePartner();

        $this->post(route('insurance.auto.request.store', ['locale' => 'en']), [
            'consent' => '1',
        ])->assertSessionHasErrors([
            'vehicle_plate', 'owner_id_number', 'contract_term_months',
        ]);

        $this->assertSame(0, AutoInsuranceRequest::count());
    }

    public function test_no_active_insurance_partners_still_lets_the_request_through_with_an_empty_results_page(): void
    {
        $response = $this->post(route('insurance.auto.request.store', ['locale' => 'en']), $this->validPayload());

        $autoInsuranceRequest = AutoInsuranceRequest::sole();
        $response->assertRedirect($autoInsuranceRequest->signedResultsUrl());
        $this->assertSame(0, AutoInsuranceQuote::count());

        $this->get($autoInsuranceRequest->signedResultsUrl())
            ->assertOk()
            ->assertSee(__('auto_insurance.results.no_quotes_yet'));
    }
}
