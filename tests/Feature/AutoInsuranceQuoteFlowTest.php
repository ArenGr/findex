<?php

namespace Tests\Feature;

use App\Models\AutoInsuranceQuote;
use App\Models\AutoInsuranceRequest;
use App\Models\Organization;
use App\Models\User;
use App\Services\Insurance\MarketQuoteDetails;
use App\Services\Insurance\MarketQuoteSourceInterface;
use App\Services\Insurance\QuoteIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the auto insurance request/results flow end to end: unlike tourism
 * there's no Telegram/secure-token round trip - every insurer is priced in
 * one call to Sil's Bureau calculator (AutoInsuranceQuoteService), and the
 * quotes land on the results page immediately.
 *
 * That single source is faked here so the flow, sorting and access rules can
 * be exercised without a live call. Sil's own two-step and icId mapping have
 * their own coverage in SilMarketQuoteSourceTest.
 */
class AutoInsuranceQuoteFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bind a market source that returns a fixed slug => premium map, standing
     * in for Sil's live response.
     *
     * @param  array<string, string>  $premiums
     */
    private function fakeMarket(array $premiums): void
    {
        $this->app->instance(MarketQuoteSourceInterface::class, new class($premiums) implements MarketQuoteSourceInterface
        {
            public function __construct(private array $premiums) {}

            public function premiums(AutoInsuranceRequest $r, QuoteIdentity $i, MarketQuoteDetails $d): array
            {
                return $this->premiums;
            }
        });
    }

    private function insurancePartner(string $slug, array $overrides = []): Organization
    {
        return Organization::create(array_merge([
            'name' => 'Insurer '.$slug,
            'slug' => $slug,
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
            // Required now: Sil will not price without these.
            'market_phone' => '+37411223344',
            'market_email' => 'quotes@example.com',
            'market_bank_account' => '1234567890123456',
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
        $a = $this->insurancePartner('ins-a');
        $b = $this->insurancePartner('ins-b');
        $this->insurancePartner('ins-inactive', ['is_active' => false]);
        Organization::create([
            'name' => 'Some Bank', 'slug' => 'some-bank', 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => true,
        ]);

        $this->fakeMarket(['ins-a' => '40000.00', 'ins-b' => '44000.00']);

        $response = $this->post(route('insurance.auto.request.store', ['locale' => 'en']), $this->validPayload());

        $autoInsuranceRequest = AutoInsuranceRequest::sole();
        $response->assertRedirect($autoInsuranceRequest->signedResultsUrl());

        $this->assertSame(2, AutoInsuranceQuote::count());
        $quotedOrgIds = AutoInsuranceQuote::pluck('organization_id')->sort()->values()->all();
        $this->assertSame([$a->id, $b->id], $quotedOrgIds);

        AutoInsuranceQuote::all()->each(function (AutoInsuranceQuote $quote) {
            $this->assertSame(AutoInsuranceQuote::STATUS_QUOTED, $quote->status);
            $this->assertNotNull($quote->premium_amount);
            $this->assertSame('AMD', $quote->premium_currency);
            $this->assertNotNull($quote->responded_at);
        });
    }

    public function test_an_insurer_missing_from_the_market_response_is_declined_not_dropped(): void
    {
        $this->insurancePartner('ins-a');
        $this->insurancePartner('ins-b');

        // Sil returned a premium for A but not B - B still appears, declined.
        $this->fakeMarket(['ins-a' => '40000.00']);

        $this->post(route('insurance.auto.request.store', ['locale' => 'en']), $this->validPayload());

        $this->assertSame(1, AutoInsuranceQuote::where('status', AutoInsuranceQuote::STATUS_QUOTED)->count());
        $this->assertSame(1, AutoInsuranceQuote::where('status', AutoInsuranceQuote::STATUS_DECLINED)->count());
    }

    public function test_results_page_shows_quotes_sorted_by_price_with_best_price_badge(): void
    {
        $this->insurancePartner('ins-cheap');
        $this->insurancePartner('ins-pricey');
        $this->fakeMarket(['ins-cheap' => '40000.00', 'ins-pricey' => '47000.00']);

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

        // The "Best price" ribbon sits at the top of the cheapest card, so it
        // renders before the pricier insurer's card - proving the badge is on
        // the first (cheapest) card, not the second.
        $badgePosition = strpos($html, __('auto_insurance.results.best_price_badge'));
        $this->assertLessThan($pricierPosition, $badgePosition);
        $this->assertSame(1, substr_count($html, __('auto_insurance.results.best_price_badge')));
    }

    public function test_show_page_requires_ownership_or_a_valid_signature(): void
    {
        $this->insurancePartner('ins-a');
        $this->fakeMarket(['ins-a' => '40000.00']);
        $this->post(route('insurance.auto.request.store', ['locale' => 'en']), $this->validPayload());
        $autoInsuranceRequest = AutoInsuranceRequest::sole();

        $this->get(route('insurance.auto.show', ['locale' => 'en', 'autoInsuranceRequest' => $autoInsuranceRequest->id]))
            ->assertForbidden();
    }

    public function test_logged_in_user_is_redirected_straight_to_the_unsigned_show_route(): void
    {
        $this->insurancePartner('ins-a');
        $this->fakeMarket(['ins-a' => '40000.00']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('insurance.auto.request.store', ['locale' => 'en']), $this->validPayload());

        $autoInsuranceRequest = AutoInsuranceRequest::sole();
        $this->assertSame($user->id, $autoInsuranceRequest->user_id);
        $response->assertRedirect(route('insurance.auto.show', ['locale' => 'en', 'autoInsuranceRequest' => $autoInsuranceRequest->id]));

        $this->get(route('insurance.auto.show', ['locale' => 'en', 'autoInsuranceRequest' => $autoInsuranceRequest->id]))
            ->assertOk();
    }

    public function test_submission_requires_vehicle_owner_and_payout_fields(): void
    {
        $this->post(route('insurance.auto.request.store', ['locale' => 'en']), [
            'consent' => '1',
        ])->assertSessionHasErrors([
            'vehicle_plate', 'owner_id_number', 'contract_term_months',
            'market_phone', 'market_email', 'market_bank_account',
        ]);

        $this->assertSame(0, AutoInsuranceRequest::count());
    }

    public function test_no_active_insurance_partners_still_lets_the_request_through_with_an_empty_results_page(): void
    {
        $this->fakeMarket([]);

        $response = $this->post(route('insurance.auto.request.store', ['locale' => 'en']), $this->validPayload());

        $autoInsuranceRequest = AutoInsuranceRequest::sole();
        $response->assertRedirect($autoInsuranceRequest->signedResultsUrl());
        $this->assertSame(0, AutoInsuranceQuote::count());

        $this->get($autoInsuranceRequest->signedResultsUrl())
            ->assertOk()
            ->assertSee(__('auto_insurance.results.empty_heading'));
    }
}
