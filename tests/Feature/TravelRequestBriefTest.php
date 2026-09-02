<?php

namespace Tests\Feature;

use App\Enums\QuoteRequestStatus;
use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Services\Telegram\TelegramClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The trip-brief half of a travel request - everything beyond "where and
 * when" that an agency needs in order to price a package: departure point,
 * date flexibility, flight/hotel/meal preferences, and priorities.
 */
class TravelRequestBriefTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(TelegramClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->andReturn(['ok' => true, 'result' => ['message_id' => 1]]);
        });
    }

    private function tourismPartner(string $countryCode = 'GE'): Organization
    {
        $organization = Organization::create([
            'name' => 'Brief Test Agency',
            'slug' => 'brief-test-agency',
            'type' => 'tourism',
            'country_code' => 'AM',
            'is_active' => true,
            'telegram_chat_id' => '123456',
        ]);

        $organization->tourismDestinations()->create(['country_code' => $countryCode]);
        User::factory()->organization($organization)->create();

        return $organization;
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'departure_location' => 'Yerevan',
            'destination_countries' => ['GE'],
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(17)->toDateString(),
            'adults' => 2,
            'children' => 1,
            'child_ages' => [8],
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'consent' => '1',
        ], $overrides);
    }

    private function submit(array $overrides = [])
    {
        return $this->post(route('tourism.request.store', ['locale' => 'en']), $this->validPayload($overrides));
    }

    public function test_the_request_form_renders_every_preference_option(): void
    {
        $response = $this->get(route('tourism.request', ['locale' => 'en']));

        $response->assertOk();
        $response->assertSee(__('tourism.request.departure_location'));
        $response->assertSee(__('tourism.request.dates_exact'));
        $response->assertSee(__('tourism.request.dates_flexible'));

        foreach (QuoteRequest::FLIGHT_PREFERENCES as $value) {
            $response->assertSee(__('tourism.flights.'.$value));
        }

        foreach (QuoteRequest::MEAL_PREFERENCES as $value) {
            $response->assertSee(__('tourism.meals.'.$value));
        }

        foreach (QuoteRequest::PRIORITIES as $value) {
            $response->assertSee(__('tourism.priorities.'.$value));
        }
    }

    /**
     * The request page is built in the travel design system (Manrope, its
     * own palette and type scale - see the travel block in app.css) rather
     * than the sitewide one. These are the classes the whole layout hangs
     * off, so losing them means the page silently reverts to looking like
     * the rest of the site.
     */
    public function test_the_request_page_renders_in_the_travel_design_system(): void
    {
        $response = $this->get(route('tourism.request', ['locale' => 'en']))->assertOk();

        foreach (['font-manrope', 'text-travel-primary', 'border-border-subtle', 'text-ink-muted', 'text-headline-md'] as $class) {
            $this->assertStringContainsString($class, $response->getContent());
        }

        // The four sections of the approved design. assertSee, not a raw
        // string check - a label like "Budget & Notes" reaches the page
        // HTML-escaped.
        $response->assertSee(__('tourism.request.section_trip'));
        $response->assertSee(__('tourism.request.section_preferences'));
        $response->assertSee(__('tourism.request.priorities_label'));
        $response->assertSee(__('tourism.request.section_budget_notes'));
    }

    public function test_the_budget_bands_are_offered_instead_of_a_free_figure(): void
    {
        $response = $this->get(route('tourism.request', ['locale' => 'en']))->assertOk();

        foreach (array_keys(QuoteRequest::BUDGET_BANDS) as $band) {
            $response->assertSee(__('tourism.budget_bands.'.$band));
        }
    }

    public function test_the_full_trip_brief_is_persisted(): void
    {
        $this->tourismPartner();

        $this->submit([
            'departure_location' => 'Yerevan',
            'date_flexibility' => QuoteRequest::DATES_PLUS_3,
            'flight_preference' => QuoteRequest::FLIGHT_INCLUDED,
            'hotel_preference' => '4',
            'meal_preference' => QuoteRequest::MEAL_ALL_INCLUSIVE,
            'priorities' => ['lowest_price', 'direct_flight'],
            'budget_currency' => 'USD',
        ]);

        $request = QuoteRequest::sole();

        $this->assertSame('Yerevan', $request->departure_location);
        $this->assertSame(QuoteRequest::DATES_PLUS_3, $request->date_flexibility);
        $this->assertTrue($request->has_flexible_dates);
        $this->assertSame(QuoteRequest::FLIGHT_INCLUDED, $request->flight_preference);
        $this->assertSame('4', $request->hotel_preference);
        $this->assertSame(QuoteRequest::MEAL_ALL_INCLUSIVE, $request->meal_preference);
        $this->assertSame(['lowest_price', 'direct_flight'], $request->priorities);
        $this->assertSame('USD', $request->budget_currency);
        $this->assertSame(7, $request->nights);
    }

    /**
     * The three preference fields are nullable rather than required - an
     * omitted one means "any"/"flexible", which is exactly what the column
     * default already says (see the rules in QuoteRequestController).
     */
    public function test_omitted_preferences_fall_back_to_their_defaults(): void
    {
        $this->tourismPartner();

        $this->submit()->assertRedirect();

        $request = QuoteRequest::sole();

        $this->assertSame(QuoteRequest::FLIGHT_FLEXIBLE, $request->flight_preference);
        $this->assertSame(QuoteRequest::HOTEL_ANY, $request->hotel_preference);
        $this->assertSame(QuoteRequest::MEAL_ANY, $request->meal_preference);
        $this->assertSame([], $request->priorities);
        $this->assertNull($request->date_flexibility);
        $this->assertFalse($request->has_flexible_dates);
    }

    /* -----------------------------------------------------------------
     * Multiple destinations, and the "open to suggestions" alternative
     * ----------------------------------------------------------------- */

    public function test_several_destinations_are_stored_as_a_list(): void
    {
        $this->tourismPartner();

        $this->submit(['destination_countries' => ['GE', 'GR']])->assertRedirect();

        $request = QuoteRequest::sole();

        $this->assertSame(['GE', 'GR'], $request->destinations);

        // The single-destination column stays in step, holding the first -
        // the Telegram brief, the emails and the destination alerts all
        // still read it (see QuoteRequest::setDestinations()).
        $this->assertSame('GE', $request->destination_country);
    }

    public function test_a_request_must_name_a_destination_or_be_open_to_suggestions(): void
    {
        $this->tourismPartner();

        $this->submit(['destination_countries' => []])
            ->assertSessionHasErrors('destination_countries');

        $this->assertSame(0, QuoteRequest::count());
    }

    /**
     * Naming nowhere is fine as long as the traveller says so - it matches
     * every agency serving any destination rather than none.
     */
    public function test_open_to_suggestions_is_accepted_without_a_destination(): void
    {
        $this->tourismPartner();

        $this->submit(['destination_countries' => [], 'open_to_suggestions' => '1'])->assertRedirect();

        $request = QuoteRequest::sole();

        $this->assertTrue($request->open_to_suggestions);
        $this->assertSame([], $request->destinations);
        $this->assertNull($request->destination_country);
        $this->assertSame(1, $request->responses()->count());
    }

    public function test_more_destinations_than_allowed_are_rejected(): void
    {
        $this->tourismPartner();

        $this->submit(['destination_countries' => array_slice(['GE', 'GR', 'CY', 'IT', 'FR', 'ES'], 0, QuoteRequest::MAX_DESTINATIONS + 1)])
            ->assertSessionHasErrors('destination_countries');

        $this->assertSame(0, QuoteRequest::count());
    }

    public function test_a_repeated_destination_is_rejected(): void
    {
        $this->tourismPartner();

        $this->submit(['destination_countries' => ['GE', 'GE']])
            ->assertSessionHasErrors('destination_countries.1');

        $this->assertSame(0, QuoteRequest::count());
    }

    /* -----------------------------------------------------------------
     * Children and their ages
     * ----------------------------------------------------------------- */

    public function test_an_age_is_stored_for_every_child(): void
    {
        $this->tourismPartner();

        $this->submit(['children' => 2, 'child_ages' => [4, 11]])->assertRedirect();

        $this->assertSame([4, 11], QuoteRequest::sole()->child_ages);
    }

    public function test_a_missing_child_age_is_rejected(): void
    {
        $this->tourismPartner();

        $this->submit(['children' => 2, 'child_ages' => [4]])
            ->assertSessionHasErrors('child_ages');

        $this->assertSame(0, QuoteRequest::count());
    }

    public function test_an_implausible_child_age_is_rejected(): void
    {
        $this->tourismPartner();

        $this->submit(['children' => 1, 'child_ages' => [42]])
            ->assertSessionHasErrors('child_ages.0');

        $this->assertSame(0, QuoteRequest::count());
    }

    public function test_no_children_needs_no_ages(): void
    {
        $this->tourismPartner();

        $this->submit(['children' => 0, 'child_ages' => []])->assertRedirect();

        $this->assertSame([], QuoteRequest::sole()->child_ages);
    }

    /* -----------------------------------------------------------------
     * Custom budget
     * ----------------------------------------------------------------- */

    public function test_a_custom_budget_range_is_stored(): void
    {
        $this->tourismPartner();

        $this->submit(['budget_min_amd' => 640000, 'budget_max_amd' => 880000])->assertRedirect();

        $request = QuoteRequest::sole();

        $this->assertSame('640000.00', $request->budget_min_amd);
        $this->assertSame('880000.00', $request->budget_max_amd);
    }

    public function test_a_custom_budget_with_the_ends_reversed_is_rejected(): void
    {
        $this->tourismPartner();

        $this->submit(['budget_min_amd' => 900000, 'budget_max_amd' => 500000])
            ->assertSessionHasErrors('budget_max_amd');

        $this->assertSame(0, QuoteRequest::count());
    }

    /**
     * A band and a custom range answer the same question. The band wins, so
     * the two can't be combined into a range nobody chose.
     */
    public function test_a_band_overrides_any_custom_range_sent_with_it(): void
    {
        $this->tourismPartner();

        $this->submit([
            'budget_band' => '500k_1m',
            'budget_min_amd' => 10,
            'budget_max_amd' => 20,
        ])->assertRedirect();

        $request = QuoteRequest::sole();

        $this->assertSame('500000.00', $request->budget_min_amd);
        $this->assertSame('1000000.00', $request->budget_max_amd);
    }

    public function test_a_departure_location_is_required(): void
    {
        $this->tourismPartner();

        $this->submit(['departure_location' => null])
            ->assertSessionHasErrors('departure_location');

        $this->assertSame(0, QuoteRequest::count());
    }

    public function test_a_preference_outside_the_offered_list_is_rejected(): void
    {
        $this->tourismPartner();

        $this->submit(['meal_preference' => 'caviar_only'])
            ->assertSessionHasErrors('meal_preference');

        $this->submit(['hotel_preference' => '7'])
            ->assertSessionHasErrors('hotel_preference');

        $this->submit(['flight_preference' => 'teleport'])
            ->assertSessionHasErrors('flight_preference');

        $this->assertSame(0, QuoteRequest::count());
    }

    public function test_more_priorities_than_allowed_are_rejected(): void
    {
        $this->tourismPartner();

        $this->submit(['priorities' => array_slice(QuoteRequest::PRIORITIES, 0, QuoteRequest::MAX_PRIORITIES + 1)])
            ->assertSessionHasErrors('priorities');

        $this->assertSame(0, QuoteRequest::count());
    }

    public function test_a_repeated_priority_is_rejected(): void
    {
        $this->tourismPartner();

        $this->submit(['priorities' => ['lowest_price', 'lowest_price']])
            ->assertSessionHasErrors('priorities.1');

        $this->assertSame(0, QuoteRequest::count());
    }

    public function test_an_unknown_priority_is_rejected(): void
    {
        $this->tourismPartner();

        $this->submit(['priorities' => ['free_upgrade']])
            ->assertSessionHasErrors('priorities.0');

        $this->assertSame(0, QuoteRequest::count());
    }

    public function test_an_arbitrary_flexibility_window_is_rejected(): void
    {
        $this->tourismPartner();

        $this->submit(['date_flexibility' => 'whenever'])->assertSessionHasErrors('date_flexibility');

        $this->assertSame(0, QuoteRequest::count());
    }

    /**
     * The form asks for a band rather than two figures - it still has to
     * land in budget_min_amd/budget_max_amd, which is what partner matching
     * reads (see QuoteRequestController::applyBudgetBand).
     */
    public function test_a_budget_band_is_stored_as_its_two_bounds(): void
    {
        $this->tourismPartner();

        $this->submit(['budget_band' => '500k_1m'])->assertRedirect();

        $request = QuoteRequest::sole();

        $this->assertSame('500000.00', $request->budget_min_amd);
        $this->assertSame('1000000.00', $request->budget_max_amd);
    }

    public function test_an_open_ended_budget_band_leaves_the_open_side_unset(): void
    {
        $this->tourismPartner();

        $this->submit(['budget_band' => 'over_2m'])->assertRedirect();

        $request = QuoteRequest::sole();

        $this->assertSame('2000000.00', $request->budget_min_amd);
        $this->assertNull($request->budget_max_amd);
    }

    /**
     * "Flexible" is a stated answer meaning no bound either way - it must
     * not smuggle in a ceiling that would filter agencies out.
     */
    public function test_a_flexible_budget_stores_no_bounds(): void
    {
        $this->tourismPartner();

        $this->submit(['budget_band' => 'flexible'])->assertRedirect();

        $request = QuoteRequest::sole();

        $this->assertNull($request->budget_min_amd);
        $this->assertNull($request->budget_max_amd);
    }

    public function test_an_unknown_budget_band_is_rejected(): void
    {
        $this->tourismPartner();

        $this->submit(['budget_band' => 'unlimited'])->assertSessionHasErrors('budget_band');

        $this->assertSame(0, QuoteRequest::count());
    }

    /**
     * Anything still submitting explicit figures - the voice extraction, or
     * any non-form caller - keeps working exactly as before.
     */
    public function test_explicit_budget_figures_are_still_accepted(): void
    {
        $this->tourismPartner();

        $this->submit(['budget_min_amd' => 700000, 'budget_max_amd' => 900000])->assertRedirect();

        $request = QuoteRequest::sole();

        $this->assertSame('700000.00', $request->budget_min_amd);
        $this->assertSame('900000.00', $request->budget_max_amd);
    }

    public function test_an_unsupported_budget_currency_is_rejected(): void
    {
        $this->tourismPartner();

        $this->submit(['budget_currency' => 'XYZ'])->assertSessionHasErrors('budget_currency');

        $this->assertSame(0, QuoteRequest::count());
    }

    public function test_end_date_before_start_date_is_rejected(): void
    {
        $this->tourismPartner();

        $this->submit([
            'check_in' => now()->addDays(17)->toDateString(),
            'check_out' => now()->addDays(10)->toDateString(),
        ])->assertSessionHasErrors('check_out');

        $this->assertSame(0, QuoteRequest::count());
    }

    public function test_a_request_with_no_adults_is_rejected(): void
    {
        $this->tourismPartner();

        $this->submit(['adults' => 0])->assertSessionHasErrors('adults');

        $this->assertSame(0, QuoteRequest::count());
    }

    /**
     * A double-tapped submit must not fan the same trip out twice - see
     * QuoteRequestController::existingOpenRequest().
     */
    public function test_resubmitting_the_same_trip_reuses_the_existing_request(): void
    {
        $this->tourismPartner();

        $this->submit()->assertRedirect();
        $this->submit()->assertRedirect();

        $this->assertSame(1, QuoteRequest::count());
    }

    public function test_the_same_country_on_different_dates_is_a_genuinely_new_request(): void
    {
        $this->tourismPartner();

        $this->submit()->assertRedirect();
        $this->submit([
            'check_in' => now()->addDays(40)->toDateString(),
            'check_out' => now()->addDays(47)->toDateString(),
        ])->assertRedirect();

        $this->assertSame(2, QuoteRequest::count());
    }

    public function test_a_new_request_starts_as_submitted_and_open(): void
    {
        $this->tourismPartner();

        $this->submit()->assertRedirect();

        $request = QuoteRequest::sole();

        $this->assertSame(QuoteRequestStatus::SUBMITTED, $request->currentStatus());
        $this->assertTrue($request->is_open);
    }

    public function test_a_request_past_its_expiry_reports_as_expired_without_being_rewritten(): void
    {
        $request = QuoteRequest::create([
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'locale' => 'en',
            'departure_location' => 'Yerevan',
            'destination_countries' => ['GE'],
            'check_in' => now()->subDays(5)->toDateString(),
            'check_out' => now()->subDays(1)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'insurance' => false,
            'expires_at' => now()->subDay(),
        ]);

        $this->assertSame(QuoteRequestStatus::EXPIRED, $request->currentStatus());
        $this->assertFalse($request->is_open);

        // Derived, never stored - the column still holds the state it was
        // last actually put into.
        $this->assertSame(QuoteRequestStatus::SUBMITTED->value, $request->getRawOriginal('status'));
    }

    public function test_a_closed_request_stays_closed_even_before_its_expiry(): void
    {
        $this->tourismPartner();
        $this->submit()->assertRedirect();

        $request = QuoteRequest::sole();
        $request->close();

        $this->assertSame(QuoteRequestStatus::CLOSED, $request->fresh()->currentStatus());
        $this->assertFalse($request->fresh()->is_open);
    }

    public function test_a_late_offer_cannot_drag_a_closed_request_back_open(): void
    {
        $this->tourismPartner();
        $this->submit()->assertRedirect();

        $request = QuoteRequest::sole();
        $request->close();
        $request->markOffersReceived();

        $this->assertSame(QuoteRequestStatus::CLOSED, $request->fresh()->currentStatus());
    }
}
