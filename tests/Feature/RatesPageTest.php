<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\CurrencyRateHistory;
use App\Models\Organization;
use App\Models\RateAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatesPageTest extends TestCase
{
    use RefreshDatabase;

    private function organization(string $slug, string $type = 'bank'): Organization
    {
        return Organization::create([
            'name' => ucfirst(str_replace('-', ' ', $slug)), 'slug' => $slug, 'type' => $type,
            'country_code' => 'AM', 'is_active' => true,
        ]);
    }

    private function rate(Organization $org, Currency $currency, float $buy, float $sell, string $type = 'cash'): CurrencyRate
    {
        return CurrencyRate::create([
            'organization_id' => $org->id, 'currency_id' => $currency->id, 'rate_type' => $type,
            'buy_rate' => $buy, 'sell_rate' => $sell, 'scraped_at' => now(),
        ]);
    }

    /** Two banks and one exchange office, quoting at clearly different levels. */
    private function seedMarket(): Currency
    {
        $usd = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1, 'is_active' => true]);

        $this->rate($this->organization('cheap-bank'), $usd, 360.0, 365.0);
        $this->rate($this->organization('pricey-bank'), $usd, 358.0, 370.0);
        $this->rate($this->organization('corner-exchange', 'exchange'), $usd, 384.0, 388.0);

        return $usd;
    }

    public function test_without_an_amount_the_page_is_a_plain_buy_and_sell_table(): void
    {
        $this->seedMarket();

        $response = $this->get('/en/rates?currency=USD');

        $response->assertOk()
            ->assertViewHas('amount', null)
            ->assertSee('>Buy', false)
            ->assertSee('>Sell', false)
            ->assertDontSee('Total you pay')
            ->assertDontSee('Current best rate');

        $this->assertSame(3, $response->viewData('ranked')['count']);
    }

    // An amount adds a column; it does not swap the table for a different one.
    public function test_an_amount_adds_a_total_column_to_the_same_table(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD&amount=500&intent=buy')
            ->assertOk()
            ->assertSee('You receive')
            ->assertSee('Current best rate')
            // The pair survives the calculation.
            ->assertSee('>Buy', false)
            ->assertSee('>Sell', false);
    }

    public function test_an_amount_turns_each_row_into_a_total(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD&amount=500')
            ->assertOk()
            ->assertViewHas('amount', 500.0)
            ->assertViewHas('intent', 'sell')
            ->assertSee('192,000')
            ->assertSee('180,000');
    }

    public function test_handing_over_dram_divides_rather_than_multiplies(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD&amount=100000&intent=buy')
            ->assertOk()
            ->assertSee('273.97')
            ->assertSee('257.73');
    }

    public function test_a_non_numeric_amount_is_ignored_rather_than_erroring(): void
    {
        $this->seedMarket();

        foreach (['abc', '-5', '0', '999999999', ''] as $bad) {
            $this->get('/en/rates?currency=USD&amount='.$bad)
                ->assertOk()
                ->assertViewHas('amount', null);
        }
    }

    public function test_buy_intent_ranks_by_the_cheapest_sell_rate(): void
    {
        $this->seedMarket();

        $response = $this->get('/en/rates?currency=USD&intent=buy');

        $response->assertOk()->assertViewHas('intent', 'buy');

        // Cheapest to buy USD across every market: the bank at 365, not the exchange office at 388.
        $ranked = $response->viewData('ranked');
        $this->assertSame(365.0, (float) $ranked['best_value'], 'buying should rank by the lowest sell rate');
        $this->assertSame('Cheap bank', $ranked['rows'][0]->organization_name);
    }

    public function test_sell_intent_flips_the_ranking_to_the_highest_buy_rate(): void
    {
        $this->seedMarket();

        $response = $this->get('/en/rates?currency=USD&intent=sell');

        $response->assertOk()->assertViewHas('intent', 'sell');

        $ranked = $response->viewData('ranked');
        $this->assertSame(384.0, (float) $ranked['best_value'], 'selling should rank by the highest buy rate');
        $this->assertSame('Corner exchange', $ranked['rows'][0]->organization_name);
    }

    public function test_banks_and_exchange_offices_share_one_ranked_table(): void
    {
        $this->seedMarket();

        $response = $this->get('/en/rates?currency=USD');
        $ranked = $response->viewData('ranked');

        $this->assertSame(3, $ranked['count'], 'every market belongs to the same list');
        $this->assertEqualsCanonicalizing(
            ['bank', 'bank', 'exchange'],
            collect($ranked['rows'])->pluck('organization_type')->all(),
        );

        // Mixed markets, so each row must say which it is.
        $response->assertSee('Exchange office')->assertSee('Bank');
    }

    public function test_a_market_tab_narrows_the_table_to_that_market(): void
    {
        $this->seedMarket();

        $ranked = $this->get('/en/rates?currency=USD&org_type=exchange')->viewData('ranked');

        $this->assertSame(1, $ranked['count']);
        $this->assertSame('exchange', $ranked['rows'][0]->organization_type);
    }

    public function test_the_spread_spans_the_best_and_worst_on_the_page(): void
    {
        $this->seedMarket();

        // Handing over USD: best buy rate 384.00, worst 358.00.
        $ranked = $this->get('/en/rates?currency=USD')->viewData('ranked');

        $this->assertSame(26.0, round((float) $ranked['spread'], 2));
    }

    public function test_the_three_best_rates_are_ranked_for_the_podium(): void
    {
        $this->seedMarket();

        $ranked = $this->get('/en/rates?currency=USD&intent=buy')->viewData('ranked');
        $byName = collect($ranked['rows'])->keyBy('organization_name');

        // Buying: 365 < 370 < 388.
        $this->assertSame(1, $byName['Cheap bank']->rank);
        $this->assertSame(2, $byName['Pricey bank']->rank);
        $this->assertSame(3, $byName['Corner exchange']->rank);
    }

    public function test_equal_rates_share_a_rank(): void
    {
        $usd = $this->seedMarket();
        $this->rate($this->organization('tied-bank'), $usd, 360.0, 365.0);

        $byName = collect($this->get('/en/rates?currency=USD&intent=buy')->viewData('ranked')['rows'])
            ->keyBy('organization_name');

        $this->assertSame(1, $byName['Cheap bank']->rank);
        $this->assertSame(1, $byName['Tied bank']->rank, 'an identical rate is joint-first');
        $this->assertSame(2, $byName['Pricey bank']->rank, 'the next distinct rate is second, not third');
    }

    public function test_rank_follows_the_rate_not_the_visitor_s_chosen_sort(): void
    {
        $this->seedMarket();

        // Sorted by spread, the cheapest row is no longer row one - the podium must still track the rate.
        $rows = collect($this->get('/en/rates?currency=USD&intent=buy&sort=spread')->viewData('ranked')['rows']);

        $this->assertNotSame('Cheap bank', $rows->first()->organization_name, 'precondition: the sort moved it');
        $this->assertSame(1, $rows->firstWhere('organization_name', 'Cheap bank')->rank);
    }

    // Sorting runs off the column headings, so the keys are column names.
    public function test_the_column_sorts_are_offered_and_an_unknown_one_falls_back(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD')
            ->assertOk()
            ->assertViewHas('sort', 'best')
            ->assertViewHas('sortOptions', ['best', 'buy', 'sell', 'spread', 'updated']);

        $this->get('/en/rates?currency=USD&lat=40.1792&lng=44.4991')
            ->assertOk()
            ->assertViewHas('sort', 'distance')
            ->assertViewHas('sortOptions', ['best', 'buy', 'sell', 'spread', 'updated', 'distance']);

        // A bookmarked link from when location sharing was on.
        $this->get('/en/rates?currency=USD&sort=distance')->assertOk()->assertViewHas('sort', 'best');
        $this->get('/en/rates?currency=USD&sort=buy_rate')->assertOk()->assertViewHas('sort', 'best');
    }

    /** Lowest spread first, which is what the option says. */
    public function test_sorting_by_spread_orders_by_the_tightest_gap(): void
    {
        $this->seedMarket();

        $rows = collect($this->get('/en/rates?currency=USD&sort=spread')->viewData('ranked')['rows']);

        // Corner exchange 388-384 = 4.00, Cheap bank 365-360 = 5.00, Pricey bank 370-358 = 12.00.
        $this->assertSame(
            ['Corner exchange', 'Cheap bank', 'Pricey bank'],
            $rows->pluck('organization_name')->all(),
        );
    }

    public function test_rates_a_fraction_apart_do_not_collapse_onto_one_rank(): void
    {
        $usd = $this->seedMarket();
        // Float array keys cast to int in PHP, so 365.50 could silently land on 365.00's rank.
        $this->rate($this->organization('halfway-bank'), $usd, 360.0, 365.5);

        $byName = collect($this->get('/en/rates?currency=USD&intent=buy')->viewData('ranked')['rows'])
            ->keyBy('organization_name');

        $this->assertSame(1, $byName['Cheap bank']->rank);
        $this->assertSame(2, $byName['Halfway bank']->rank);
    }

    public function test_only_rate_types_that_have_rows_are_offered(): void
    {
        $usd = $this->seedMarket();
        $this->rate($this->organization('card-bank'), $usd, 355.0, 372.0, 'card');

        $types = $this->get('/en/rates?currency=USD')->viewData('availableTypes');

        $this->assertEqualsCanonicalizing(['cash', 'card'], $types->all());
        $this->assertNotContains('transfer', $types->all(), 'a type with no rows must not be offered');
    }

    public function test_a_dead_end_combination_suggests_a_type_that_has_data(): void
    {
        $this->seedMarket(); // cash only

        $response = $this->get('/en/rates?currency=USD&type=transfer');

        $response->assertOk()
            ->assertViewHas('suggestedType', 'cash')
            ->assertSee('Show Cash rates instead');
    }

    public function test_changing_bank_or_city_preserves_an_active_nearby_search(): void
    {
        $this->seedMarket();

        $this->branch(Organization::where('slug', 'cheap-bank')->firstOrFail(), 'Centre', 40.18, 44.51);
        $this->branch(Organization::where('slug', 'pricey-bank')->firstOrFail(), 'North', 40.79, 43.85, 'Gyumri');

        // org_type, so the bank menu is offered alongside the city one.
        $html = $this->get('/en/rates?currency=USD&org_type=bank&lat=40.1792&lng=44.4991')
            ->assertOk()
            ->getContent();

        preg_match_all('/(?:href|value)="([^"]*city=[^"]*)"/', $html, $cityLinks);
        preg_match_all('/(?:href|value)="([^"]*organization=[^"]*)"/', $html, $bankLinks);

        $this->assertNotEmpty($cityLinks[1], 'precondition: the panel offers city links');
        $this->assertNotEmpty($bankLinks[1], 'precondition: the panel offers bank links');
        $matches = [1 => [...$cityLinks[1], ...$bankLinks[1]]];

        foreach ($matches[1] as $href) {
            $href = html_entity_decode($href);
            $this->assertStringContainsString('lat=40.1792', $href, "link drops lat: {$href}");
            $this->assertStringContainsString('lng=44.4991', $href, "link drops lng: {$href}");
        }

        // And the search form, which is the one GET form left on the page.
        $this->assertStringContainsString('name="lat" value="40.1792"', $html);
        $this->assertStringContainsString('name="lng" value="44.4991"', $html);
    }

    public function test_the_quote_cta_states_the_amount_that_qualifies(): void
    {
        $this->seedMarket();

        // config('exchange-quotes.minimum_amounts.USD') is 1000.
        $this->get('/en/rates?currency=USD&amount=500')
            ->assertOk()
            ->assertSee('Exchanging more than 1,000 USD?');
    }

    public function test_the_quote_cta_changes_once_the_amount_qualifies(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD&amount=5000')
            ->assertOk()
            ->assertSee('You are exchanging 5,000 USD')
            ->assertDontSee('Exchanging more than 1,000 USD?');
    }

    public function test_the_quote_cta_is_hidden_for_a_currency_with_no_minimum(): void
    {
        $jpy = Currency::create(['code' => 'JPY', 'name' => 'Yen', 'symbol' => '¥', 'sort_order' => 2, 'is_active' => true]);
        $this->rate($this->organization('jpy-bank'), $jpy, 2.4, 2.6);

        $this->get('/en/rates?currency=JPY')
            ->assertOk()
            ->assertViewHas('quoteMinimum', null)
            ->assertDontSee('Negotiate your rate');
    }

    public function test_the_organization_filter_names_the_market_it_lists(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD')
            ->assertOk()
            ->assertDontSee('All banks')
            ->assertDontSee('All exchange offices');

        $this->get('/en/rates?currency=USD&org_type=bank')
            ->assertOk()
            ->assertSee('All banks')
            ->assertDontSee('All exchange offices');

        // The label used to say "All banks" here while listing exchange offices.
        $this->get('/en/rates?currency=USD&org_type=exchange')
            ->assertOk()
            ->assertSee('All exchange offices')
            ->assertDontSee('All banks');
    }

    public function test_switching_market_clears_a_now_impossible_organization(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD&org_type=exchange&organization=corner-exchange')
            ->assertOk()
            ->assertDontSee('org_type=bank&amp;organization=corner-exchange');
    }

    public function test_rates_from_inactive_organizations_are_never_listed(): void
    {
        $usd = $this->seedMarket();
        $hidden = $this->organization('hidden-bank');
        $hidden->update(['is_active' => false]);
        $this->rate($hidden, $usd, 999.0, 999.0);

        $this->get('/en/rates?currency=USD')
            ->assertOk()
            ->assertDontSee('Hidden bank');
    }

    // Six banks quoting 368.00 all hold rank 1.
    public function test_the_best_rate_is_stated_once_above_the_table(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD&intent=buy&amount=100')
            ->assertOk()
            ->assertSee('Current best rate')
            ->assertSee('You receive');
    }

    public function test_the_spread_column_is_always_present(): void
    {
        $this->seedMarket();

        foreach (['', '&amount=100', '&both=1'] as $extra) {
            $this->get('/en/rates?currency=USD'.$extra)
                ->assertOk()
                ->assertSee('Spread')
                // The toggle that used to control it is gone.
                ->assertDontSee('Detailed');
        }
    }

    /** The pair is on screen in every one of those four states. */
    public function test_the_rate_pair_is_always_on_screen(): void
    {
        $this->seedMarket();

        foreach (['', '&amount=100', '&sort=spread', '&sort=updated&amount=100'] as $extra) {
            $this->get('/en/rates?currency=USD'.$extra)
                ->assertOk()
                ->assertSee('>Buy', false)
                ->assertSee('>Sell', false);
        }
    }

    public function test_a_shared_best_rate_says_how_many_organizations_hold_it(): void
    {
        $usd = $this->seedMarket();
        $this->rate($this->organization('tied-bank'), $usd, 384.0, 391.0);

        // Two organizations now share the top buy rate of 384.00.
        $this->get('/en/rates?currency=USD')
            ->assertOk()
            ->assertSee('Best rate — available at 2 organizations');
    }

    /** A single winner is just "Best" - there is nothing to account for. */
    public function test_an_outright_best_rate_is_not_described_as_shared(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD')
            ->assertOk()
            ->assertDontSee('available at');
    }

    public function test_stale_rates_are_flagged_in_words_not_only_in_colour(): void
    {
        $usd = $this->seedMarket();
        CurrencyRate::query()->update(['scraped_at' => now()->subDays(3)]);

        $this->get('/en/rates?currency='.$usd->code)
            ->assertOk()
            ->assertSee('Rates older than a day');
    }

    /** A fresh table says nothing about staleness at all. */
    public function test_fresh_rates_carry_no_warning(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD')
            ->assertOk()
            ->assertDontSee('Rates older than a day');
    }

    // When a rate last moved, as opposed to when it was last looked at.
    public function test_a_row_says_when_its_rate_last_changed(): void
    {
        $usd = $this->seedMarket();
        $rate = CurrencyRate::query()->firstOrFail();

        CurrencyRateHistory::create([
            'currency_rate_id' => $rate->id,
            'buy_rate' => 350.0,
            'sell_rate' => 355.0,
            'scraped_at' => now()->subWeek(),
        ]);

        $this->get('/en/rates?currency=USD')
            ->assertOk()
            ->assertSee('Rate unchanged since 1 week ago');
    }

    /** No history means we have never seen it move, which is not a claim. */
    public function test_a_rate_with_no_history_makes_no_claim_about_changing(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD')->assertOk()->assertDontSee('Rate unchanged since');
    }

    public function test_the_best_result_is_compared_against_the_market_average(): void
    {
        $this->seedMarket();

        // Handing over 100 USD.
        $this->get('/en/rates?currency=USD&amount=100')
            ->assertOk()
            ->assertSee('367.33')
            ->assertSee('You get 1,667 AMD more than the market average.');
    }

    /** One organization means the average IS the best rate; the claim is noise. */
    public function test_no_average_comparison_when_there_is_nothing_to_compare(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD&org_type=exchange&amount=100')
            ->assertOk()
            ->assertDontSee('more than the market average');
    }

    // Everything /rates already knows travels to the negotiation form.
    public function test_the_negotiation_link_carries_the_whole_exchange_context(): void
    {
        $this->seedMarket();
        Branch::create(['organization_id' => Organization::firstOrFail()->id, 'name' => 'B', 'city' => 'Yerevan', 'is_active' => true]);

        $html = $this->get('/en/rates?currency=USD&amount=5000&city=Yerevan&intent=sell')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('currency=USD', $html);
        $this->assertMatchesRegularExpression('/exchange\?[^"]*amount=5000/', $html);
        $this->assertMatchesRegularExpression('/exchange\?[^"]*city=Yerevan/', $html);
        $this->assertMatchesRegularExpression('/exchange\?[^"]*rate_field=buy_rate/', $html);
    }

    public function test_a_dram_amount_is_converted_before_it_reaches_the_exchange_form(): void
    {
        $this->seedMarket();

        // 5,000 AMD at the cheapest sell rate of 365.00 buys 13.70 USD.
        $html = $this->get('/en/rates?currency=USD&amount=5000&intent=buy')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/exchange\?[^"]*amount=13\.7/', $html);
        $this->assertDoesNotMatchRegularExpression('/exchange\?[^"]*amount=5000/', $html);
    }

    // Below the minimum the amount still travels.
    public function test_an_amount_below_the_quote_minimum_still_travels(): void
    {
        $this->seedMarket();

        $html = $this->get('/en/rates?currency=USD&amount=50')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/exchange\?[^"]*amount=50/', $html);
    }

    public function test_the_list_is_the_default_and_the_map_is_opt_in(): void
    {
        $this->seedMarket();

        Branch::create([
            'organization_id' => Organization::where('slug', 'cheap-bank')->firstOrFail()->id,
            'name' => 'Kentron', 'city' => 'Yerevan',
            'latitude' => 40.1792, 'longitude' => 44.4991, 'is_active' => true,
        ]);

        $this->get('/en/rates?currency=USD')
            ->assertOk()
            ->assertViewHas('viewMode', 'list')
            ->assertSee('<table', false)
            ->assertDontSee('data-rates-map', false);

        $this->get('/en/rates?currency=USD&view=map')
            ->assertOk()
            ->assertViewHas('viewMode', 'map')
            ->assertSee('data-rates-map', false)
            ->assertDontSee('<table', false);

        $this->get('/en/rates?currency=USD&view=nonsense')->assertOk()->assertViewHas('viewMode', 'list');
    }

    public function test_every_geocoded_branch_becomes_a_pin(): void
    {
        $this->seedMarket();
        $bank = Organization::where('slug', 'cheap-bank')->firstOrFail();

        Branch::create(['organization_id' => $bank->id, 'name' => 'Kentron', 'city' => 'Yerevan',
            'latitude' => 40.1792, 'longitude' => 44.4991, 'is_active' => true]);
        Branch::create(['organization_id' => $bank->id, 'name' => 'Arabkir', 'city' => 'Yerevan',
            'latitude' => 40.2000, 'longitude' => 44.5100, 'is_active' => true]);
        // No coordinates: pinning it would put it in the Gulf of Guinea.
        Branch::create(['organization_id' => $bank->id, 'name' => 'Nowhere', 'city' => 'Yerevan', 'is_active' => true]);
        // Inactive branches are not places you can walk into.
        Branch::create(['organization_id' => $bank->id, 'name' => 'Closed', 'city' => 'Yerevan',
            'latitude' => 40.21, 'longitude' => 44.52, 'is_active' => false]);

        $branches = $this->get('/en/rates?currency=USD&view=map')->assertOk()->viewData('mapBranches');

        $this->assertCount(2, $branches[$bank->id]);
        $this->assertEqualsCanonicalizing(['Kentron', 'Arabkir'], array_column($branches[$bank->id], 'name'));
    }

    /** Nothing to plot is said plainly rather than shown as an empty grey box. */
    public function test_the_map_says_so_when_no_branch_has_been_mapped(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD&view=map')
            ->assertOk()
            ->assertSee('No mapped branches for these rates yet.');
    }

    // Narrowing by name describes the table.
    public function test_table_only_controls_stand_down_in_map_view(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD')->assertOk()
            ->assertSee('Search by name')
            ->assertSee('Spread');

        $this->get('/en/rates?currency=USD&view=map')->assertOk()
            ->assertDontSee('Search by name')
            ->assertDontSee('Spread');
    }

    public function test_open_now_keeps_only_organizations_with_a_branch_open(): void
    {
        $this->seedMarket();
        $bank = Organization::where('slug', 'cheap-bank')->firstOrFail();
        $exchange = Organization::where('slug', 'corner-exchange')->firstOrFail();

        $office = ['mon' => ['09:30', '17:30'], 'sat' => null, 'sun' => null];
        $allHours = ['mon' => ['09:00', '21:00'], 'sat' => ['10:00', '20:00'], 'sun' => ['10:00', '18:00']];

        Branch::create(['organization_id' => $bank->id, 'name' => 'Bank branch', 'city' => 'Yerevan',
            'is_active' => true, 'opening_hours' => $office]);
        Branch::create(['organization_id' => $exchange->id, 'name' => 'Exchange branch', 'city' => 'Yerevan',
            'is_active' => true, 'opening_hours' => $allHours]);

        // Monday 10:00 in Yerevan is 06:00 UTC: both trade.
        $this->travelTo('2026-08-17 06:00:00');
        $this->assertSame(2, $this->get('/en/rates?currency=USD&open=1')->viewData('ranked')['count']);

        // Monday 18:00 Yerevan: the bank has shut, the office has not.
        $this->travelTo('2026-08-17 14:00:00');
        $rows = $this->get('/en/rates?currency=USD&open=1')->viewData('ranked')['rows'];
        $this->assertSame(['Corner exchange'], collect($rows)->pluck('organization_name')->all());

        // Sunday 19:00 Yerevan: nobody.
        $this->travelTo('2026-08-16 15:00:00');
        $this->assertSame(0, $this->get('/en/rates?currency=USD&open=1')->viewData('ranked')['count']);

        // ...and without the filter the table is untouched by any of it.
        $this->assertSame(3, $this->get('/en/rates?currency=USD')->viewData('ranked')['count']);
    }

    public function test_a_branch_without_hours_is_never_claimed_to_be_open(): void
    {
        $this->seedMarket();
        Branch::create([
            'organization_id' => Organization::where('slug', 'cheap-bank')->firstOrFail()->id,
            'name' => 'Unknown hours', 'city' => 'Yerevan', 'is_active' => true,
        ]);

        $this->travelTo('2026-08-17 06:00:00');

        $this->assertSame(0, $this->get('/en/rates?currency=USD&open=1')->viewData('ranked')['count']);
    }

    /** The always-visible bar signals an active filter on the control itself. */
    public function test_the_open_now_toggle_reflects_whether_it_is_active(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD')->assertOk()->assertSee('aria-pressed="false"', false);
        $this->get('/en/rates?currency=USD&open=1')->assertOk()->assertSee('aria-pressed="true"', false);
    }

    /** Scraped numbers, so the page says what that means for trusting them. */
    public function test_the_page_carries_a_disclaimer_about_the_rates(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD')
            ->assertOk()
            ->assertSee('the rate you are given at the counter is the one that counts');
    }

    public function test_filtering_narrows_the_table(): void
    {
        $this->seedMarket();

        $this->assertSame(3, $this->get('/en/rates?currency=USD')->viewData('ranked')['count']);
        $this->assertSame(1, $this->get('/en/rates?currency=USD&org_type=exchange')->viewData('ranked')['count']);
    }

    public function test_the_table_carries_an_updated_column(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD')->assertOk()->assertSee('Updated');
    }

    public function test_the_summary_cards_state_the_best_of_each_side(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD')
            ->assertOk()
            ->assertSee('Best buy rate')
            ->assertSee('384.00')
            ->assertSee('Best sell rate')
            ->assertSee('365.00')
            ->assertSee('Market average')
            ->assertSee('367.33')
            ->assertSee('Across 3 organizations');
    }

    /** Flipping the direction moves the average to the other column. */
    public function test_the_average_follows_the_side_being_exchanged(): void
    {
        $this->seedMarket();

        // Buy side: (360 + 358 + 384) / 3 = 367.33.
        $this->get('/en/rates?currency=USD&intent=sell')->assertOk()->assertSee('367.33');

        // Sell side: (365 + 370 + 388) / 3 = 374.33.
        $this->get('/en/rates?currency=USD&intent=buy')->assertOk()->assertSee('374.33');
    }

    // The cards stay put when an amount is entered, and the best-rate band joins them.
    public function test_the_summary_cards_survive_a_calculation(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD&amount=100')
            ->assertOk()
            ->assertSee('Market average')
            ->assertSee('Best buy rate')
            ->assertSee('Current best rate');
    }

    public function test_the_central_bank_rate_is_a_reference_line_not_a_filter(): void
    {
        $usd = $this->seedMarket();
        $cba = $this->organization('central-bank');
        $this->rate($cba, $usd, 366.17, 366.17, 'central_bank');

        $response = $this->get('/en/rates?currency=USD');

        $response->assertOk()
            ->assertSee('Central Bank reference rate: 1 USD = 366.17 AMD')
            ->assertDontSee('>Central Bank<', false);

        $this->assertNotContains('central_bank', $response->viewData('availableTypes')->all());
    }

    // Every alert route is behind auth.
    public function test_the_alert_modal_asks_a_guest_to_sign_in_before_the_form(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD')
            ->assertOk()
            ->assertSee('Rate alerts are tied to your account', false)
            ->assertDontSee('name="threshold"', false);
    }

    public function test_the_alert_modal_is_prefilled_from_what_is_on_screen(): void
    {
        $this->seedMarket();

        $html = $this->actingAs(User::factory()->create())
            ->get('/en/rates?currency=USD&intent=buy&type=cash')
            ->assertOk()
            ->assertSee('name="threshold"', false)
            ->getContent();

        $this->assertSame([
            'currency_id' => (string) Currency::where('code', 'USD')->value('id'),
            'organization_id' => '',
            'rate_type' => 'cash',
            'rate_field' => 'sell_rate',
            'direction' => 'below',
            'threshold' => '365.00',
        ], $this->alertPrefill($html));
    }

    /** Selling watches the other side of the pair, and waits for a rise. */
    public function test_selling_flips_the_prefilled_field_and_condition(): void
    {
        $this->seedMarket();

        $html = $this->actingAs(User::factory()->create())
            ->get('/en/rates?currency=USD&intent=sell&type=cash')
            ->assertOk()
            ->getContent();

        $prefill = $this->alertPrefill($html);

        $this->assertSame('buy_rate', $prefill['rate_field']);
        $this->assertSame('above', $prefill['direction']);
    }

    /**
     * The trigger hands the modal its prefill as a JSON payload on a CustomEvent.
     *
     * @return array<string, string>
     */
    private function alertPrefill(string $html): array
    {
        $this->assertMatchesRegularExpression("/rate-alert-open', \{ detail: JSON\.parse\('/", $html);

        preg_match("/rate-alert-open', \{ detail: JSON\.parse\('(.+?)'\)/", $html, $matches);

        return json_decode(json_decode('"'.$matches[1].'"'), true)['form'];
    }

    public function test_creating_an_alert_from_rates_returns_to_the_same_filtered_view(): void
    {
        $this->seedMarket();
        $user = User::factory()->create();
        $return = url('/en/rates?currency=USD&intent=sell');

        $this->actingAs($user)->post('/en/alerts', [
            'currency_id' => Currency::where('code', 'USD')->value('id'),
            'rate_type' => 'cash',
            'rate_field' => 'buy_rate',
            'direction' => 'above',
            'threshold' => 370,
            'channel' => 'email',
            'return_to' => $return,
        ])->assertRedirect($return);

        $this->assertSame(1, RateAlert::where('user_id', $user->id)->count());
    }

    /** An unvalidated return_to is an open redirect. */
    public function test_an_offsite_return_to_is_ignored(): void
    {
        $this->seedMarket();

        $this->actingAs(User::factory()->create())->post('/en/alerts', [
            'currency_id' => Currency::where('code', 'USD')->value('id'),
            'rate_type' => 'cash',
            'rate_field' => 'buy_rate',
            'direction' => 'above',
            'threshold' => 370,
            'channel' => 'email',
            'return_to' => 'https://evil.example.com/phish',
        ])->assertRedirect(route('alerts.index'));
    }

    public function test_the_modal_only_offers_channels_the_account_can_receive_on(): void
    {
        $this->seedMarket();

        $this->actingAs(User::factory()->create())
            ->get('/en/rates?currency=USD')
            ->assertOk()
            ->assertSee('value="email"', false)
            ->assertDontSee('value="telegram"', false);

        $this->actingAs(User::factory()->create(['telegram_chat_id' => '12345']))
            ->get('/en/rates?currency=USD')
            ->assertOk()
            ->assertSee('value="telegram"', false);
    }

    public function test_a_small_total_keeps_the_decimals_that_distinguish_it(): void
    {
        $kzt = Currency::create(['code' => 'KZT', 'name' => 'Tenge', 'symbol' => '\u{20b8}', 'sort_order' => 2, 'is_active' => true]);

        $this->rate($this->organization('low-office', 'exchange'), $kzt, 0.76, 0.80);
        $this->rate($this->organization('high-office', 'exchange'), $kzt, 0.77, 0.81);

        $this->get('/en/rates?currency=KZT&amount=1&intent=sell')
            ->assertOk()
            ->assertSee('0.77')
            ->assertSee('0.76')
            ->assertDontSee('>1 AMD<', false)
            ->assertSee('0.01 AMD');
    }

    /** Above a thousand a rounded half-dram is noise, so it still rounds. */
    public function test_a_large_total_is_still_shown_as_whole_dram(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD&amount=500&intent=sell')
            ->assertOk()
            ->assertSee('192,000')
            ->assertDontSee('192,000.00');
    }

    public function test_the_best_rate_stops_calling_itself_current_once_it_is_stale(): void
    {
        $usd = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1, 'is_active' => true]);
        $rate = $this->rate($this->organization('fresh-bank'), $usd, 360.0, 365.0);
        $rate->update(['scraped_at' => now()->subMinutes(10)]);

        $this->get('/en/rates?currency=USD&amount=100')
            ->assertOk()
            ->assertSee('Current best rate')
            ->assertDontSee('Best available rate');

        $rate->update(['scraped_at' => now()->subDays(3)]);

        $this->get('/en/rates?currency=USD&amount=100')
            ->assertOk()
            ->assertSee('Best available rate')
            ->assertDontSee('Current best rate');
    }

    /** The spread restated as something that happens to the visitor. */
    public function test_the_spread_is_framed_as_what_the_visitor_keeps(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD&amount=100&intent=sell')
            ->assertOk()
            ->assertSee('Choosing the best rate here earns you 2,600 AMD more.');

        // Handing over 100,000 AMD: 100000/365 - 100000/388 = 16.24 USD.
        $this->get('/en/rates?currency=USD&amount=100000&intent=buy')
            ->assertOk()
            ->assertSee('earns you 16.24 USD more.');
    }

    private function branch(Organization $org, string $name, float $lat, float $lng, ?string $city = 'Yerevan'): Branch
    {
        return Branch::create([
            'organization_id' => $org->id, 'name' => $name, 'city' => $city,
            'latitude' => $lat, 'longitude' => $lng, 'is_active' => true,
        ]);
    }

    public function test_directions_appear_only_when_one_branch_is_identifiable(): void
    {
        $usd = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1, 'is_active' => true]);

        $single = $this->organization('one-branch-bank');
        $this->branch($single, 'Only', 40.1811, 44.5136);
        $this->rate($single, $usd, 360.0, 365.0);

        $many = $this->organization('two-branch-bank');
        $this->branch($many, 'Kentron', 40.1770, 44.5100);
        $this->branch($many, 'Far away', 40.1611, 44.2916, 'Vagharshapat');
        $this->rate($many, $usd, 359.0, 366.0);

        $this->get('/en/rates?currency=USD')
            ->assertOk()
            ->assertSee('destination=40.1811,44.5136', false)
            ->assertSee('Two branch bank')
            ->assertDontSee('destination=40.177', false);
    }

    /** A city filter disambiguates just as well as location does. */
    public function test_a_city_filter_identifies_the_branch_to_walk_to(): void
    {
        $usd = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1, 'is_active' => true]);

        $org = $this->organization('two-branch-bank');
        $this->branch($org, 'Kentron', 40.1770, 44.5100);
        $this->branch($org, 'Far away', 40.1611, 44.2916, 'Vagharshapat');
        $this->rate($org, $usd, 359.0, 366.0);

        $this->get('/en/rates?currency=USD')
            ->assertOk()
            ->assertDontSee('maps/dir', false);

        $this->get('/en/rates?currency=USD&city=Vagharshapat')
            ->assertOk()
            ->assertSee('destination=40.1611,44.2916', false);
    }

    public function test_with_location_directions_point_at_the_nearest_branch(): void
    {
        $usd = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1, 'is_active' => true]);

        $org = $this->organization('two-branch-bank');
        // Deliberately created far-first: taking the first row would pick this.
        $this->branch($org, 'Far away', 40.1611, 44.2916, 'Vagharshapat');
        $this->branch($org, 'Kentron', 40.1770, 44.5100);
        $this->rate($org, $usd, 359.0, 366.0);

        $this->get('/en/rates?currency=USD&lat=40.1792&lng=44.4991&sort=distance&direction=asc')
            ->assertOk()
            ->assertSee('destination=40.177,44.51', false)
            ->assertDontSee('destination=40.1611,44.2916', false);
    }

    /** An organization with no branches on file simply has nowhere to send you. */
    public function test_an_organization_without_branches_has_no_directions_link(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD')->assertOk()->assertDontSee('maps/dir', false);
    }

    public function test_the_filter_bar_reflects_a_non_default_selection(): void
    {
        $this->seedMarket();

        $html = $this->get('/en/rates?currency=USD&org_type=exchange')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="[^"]*org_type=exchange[^"]*"[^>]*data-filter-option[^>]*aria-current="true"/',
            $html,
        );
    }

    // Eleven chips turn one decision into eleven and push the rates off the screen.
    public function test_only_the_everyday_currencies_are_shown_at_first(): void
    {
        $this->seedMarket();
        $chf = Currency::create(['code' => 'CHF', 'name' => 'Franc', 'symbol' => 'Fr', 'sort_order' => 4, 'is_active' => true]);
        $gel = Currency::create(['code' => 'GEL', 'name' => 'Lari', 'symbol' => '\u{20be}', 'sort_order' => 6, 'is_active' => true]);

        $response = $this->get('/en/rates?currency=USD')->assertOk();

        $response->assertSee('currency=CHF', false)
            ->assertSee('currency=GEL', false)
            // ...but the page opens collapsed, and says how many are behind it.
            ->assertSee('x-data="{ showAll: false }"', false)
            ->assertSee('More currencies');

        $this->assertSame('CHF', $chf->code);
        $this->assertSame('GEL', $gel->code);
    }

    public function test_landing_on_a_hidden_currency_opens_the_rest(): void
    {
        $this->seedMarket();
        Currency::create(['code' => 'CHF', 'name' => 'Franc', 'symbol' => 'Fr', 'sort_order' => 4, 'is_active' => true]);

        $this->get('/en/rates?currency=CHF')
            ->assertOk()
            ->assertSee('x-data="{ showAll: true }"', false);

        // An everyday one leaves them collapsed.
        $this->get('/en/rates?currency=USD')
            ->assertOk()
            ->assertSee('x-data="{ showAll: false }"', false);
    }

    /** With nothing beyond the everyday four, there is nothing to expand. */
    public function test_no_button_when_every_currency_is_already_shown(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD')
            ->assertOk()
            ->assertDontSee('More currencies');
    }

    public function test_a_column_sorts_the_way_it_is_usually_asked(): void
    {
        $this->seedMarket();

        // Corner exchange 384/388, Cheap bank 360/365, Pricey bank 358/370.
        $first = fn (string $query) => collect(
            $this->get('/en/rates?currency=USD&'.$query)->viewData('ranked')['rows']
        )->first()->organization_name;

        $this->assertSame('Corner exchange', $first('sort=buy'), 'buy: highest first');
        $this->assertSame('Cheap bank', $first('sort=sell'), 'sell: lowest first');
        $this->assertSame('Corner exchange', $first('sort=spread'), 'spread: tightest first');
    }

    /** Pressing the same heading again reverses it. */
    public function test_a_direction_can_be_reversed_and_a_bad_one_ignored(): void
    {
        $this->seedMarket();

        $first = fn (string $query) => collect(
            $this->get('/en/rates?currency=USD&'.$query)->viewData('ranked')['rows']
        )->first()->organization_name;

        $this->assertSame('Pricey bank', $first('sort=buy&dir=asc'), 'buy reversed: lowest first');
        $this->assertSame('Pricey bank', $first('sort=spread&dir=desc'), 'spread reversed: widest first');

        // Nonsense falls back to the column's own direction rather than ordering by nothing.
        $this->assertSame('Corner exchange', $first('sort=buy&dir=sideways'));
        $this->get('/en/rates?currency=USD&sort=buy&dir=DESC')->assertOk()->assertViewHas('direction', 'desc');
    }

    public function test_the_default_ordering_marks_its_own_column(): void
    {
        $this->seedMarket();

        // Selling USD ranks on the buy column; buying it ranks on sell.
        $this->get('/en/rates?currency=USD&intent=sell')->assertOk()
            ->assertSee('sort=sell', false)
            ->assertSee('sorted highest first');

        $this->get('/en/rates?currency=USD&intent=buy')->assertOk()
            ->assertSee('sorted lowest first');
    }

    // Finding one organization among fourteen was a job the page had no answer for.
    public function test_searching_narrows_the_table_by_name(): void
    {
        $this->seedMarket();

        $names = fn (string $query) => collect(
            $this->get('/en/rates?currency=USD&'.$query)->viewData('ranked')['rows']
        )->pluck('organization_name')->all();

        $this->assertSame(['Corner exchange'], $names('q=corner'));
        // Case and partial words both match - people type what they remember.
        $this->assertSame(['Corner exchange'], $names('q=CORNER'));
        $this->assertEqualsCanonicalizing(['Cheap bank', 'Pricey bank'], $names('q=bank'));
        $this->assertSame([], $names('q=nothing here'));
    }

    public function test_an_empty_search_result_offers_to_clear_the_search(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD&q=zzzz')
            ->assertOk()
            // Escaped, because the term is whatever was typed.
            ->assertSee('No organization here matches &quot;zzzz&quot;.', false)
            ->assertSee('Clear search')
            ->assertDontSee('No rates match');
    }

    /** A search term is state like any other, so it survives every control. */
    public function test_the_search_term_is_carried_by_the_other_controls(): void
    {
        $this->seedMarket();

        $html = $this->get('/en/rates?currency=USD&q=bank&sort=spread')->assertOk()->getContent();

        // On the links that change something else...
        $this->assertStringContainsString('q=bank', $html);
        // ...and back into the field itself, so it does not look cleared.
        $this->assertStringContainsString('value="bank"', $html);
        // The sort survives the search form's own submit.
        $this->assertStringContainsString('name="sort" value="spread"', $html);
    }

    /** Long enough to be a name, short enough not to be a payload. */
    public function test_an_overlong_search_term_is_truncated_rather_than_refused(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD&q='.str_repeat('a', 500))
            ->assertOk()
            ->assertViewHas('search', str_repeat('a', 60));
    }

    // Ten rows a page, but the page is ranked, averaged and starred against the whole market.
    public function test_paging_shows_ten_rows_while_ranking_the_whole_market(): void
    {
        $usd = $this->seedMarket();

        foreach (range(1, 12) as $n) {
            $this->rate($this->organization("filler-{$n}"), $usd, 300.0 + $n, 400.0 - $n);
        }

        $first = $this->get('/en/rates?currency=USD')->assertOk();
        $second = $this->get('/en/rates?currency=USD&page=2')->assertOk();

        $this->assertCount(10, $first->viewData('pageRows'));
        $this->assertCount(5, $second->viewData('pageRows'));

        // Both pages agree on the market, because both were ranked over it.
        foreach ([$first, $second] as $response) {
            $this->assertSame(15, $response->viewData('ranked')['count']);
            $this->assertSame(384.0, (float) $response->viewData('ranked')['best_value']);
        }

        // And rank 1 is a page-one row, not the best of whatever page you are on.
        $this->assertSame(1, collect($first->viewData('pageRows'))->firstWhere('organization_name', 'Corner exchange')->rank);
        $this->assertNotContains(1, collect($second->viewData('pageRows'))->pluck('rank')->all());
    }

    /** A page past the end is empty rather than an error or a silent page one. */
    public function test_a_page_beyond_the_last_is_not_an_error(): void
    {
        $this->seedMarket();

        $response = $this->get('/en/rates?currency=USD&page=99')->assertOk();

        $this->assertSame([], $response->viewData('pageRows'));
        // The market is still described in full above the empty page.
        $this->assertSame(3, $response->viewData('ranked')['count']);
    }

    /** The search narrows the market, so it narrows the paging with it. */
    public function test_searching_repages_the_result(): void
    {
        $usd = $this->seedMarket();

        foreach (range(1, 12) as $n) {
            $this->rate($this->organization("filler-{$n}"), $usd, 300.0 + $n, 400.0 - $n);
        }

        $response = $this->get('/en/rates?currency=USD&q=bank')->assertOk();

        // Cheap bank and Pricey bank - "filler-N" matches neither.
        $this->assertSame(2, $response->viewData('ranked')['count']);
        $this->assertCount(2, $response->viewData('pageRows'));
    }

    public function test_no_pagination_when_everything_fits(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD')
            ->assertOk()
            ->assertDontSee('Pages of rates');
    }

    /** Paging is a view of the same filtered market, so it carries the state. */
    public function test_page_links_carry_the_current_filters(): void
    {
        $usd = $this->seedMarket();

        foreach (range(1, 12) as $n) {
            $this->rate($this->organization("filler-{$n}"), $usd, 300.0 + $n, 400.0 - $n);
        }

        $html = $this->get('/en/rates?currency=USD&sort=spread&dir=desc')->assertOk()->getContent();

        preg_match_all('/href="([^"]*page=2[^"]*)"/', $html, $matches);
        $this->assertNotEmpty($matches[1], 'precondition: there is a second page to link to');

        foreach ($matches[1] as $href) {
            $href = html_entity_decode($href);
            $this->assertStringContainsString('sort=spread', $href);
            $this->assertStringContainsString('dir=desc', $href);
        }
    }

    public function test_the_rate_pair_is_coloured_the_same_way_in_the_table(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD')
            ->assertOk()
            ->assertSee('text-primary tabular-nums', false)
            ->assertSee('tabular-nums text-accent-red', false);
    }

    public function test_the_map_is_not_paged(): void
    {
        $usd = $this->seedMarket();

        foreach (range(1, 12) as $n) {
            $organization = $this->organization("filler-{$n}");
            $this->rate($organization, $usd, 300.0 + $n, 400.0 - $n);
            $this->branch($organization, "Branch {$n}", 40.1 + ($n / 100), 44.5);
        }

        // The list pages, and says so.
        $this->get('/en/rates?currency=USD')->assertOk()->assertSee('Pages of rates');

        // The map does neither, and still plots everything.
        $map = $this->get('/en/rates?currency=USD&view=map')->assertOk();
        $map->assertDontSee('Pages of rates');
        $this->assertGreaterThan(10, count($map->viewData('mapBranches')));
    }

    public function test_a_missing_everyday_config_does_not_break_the_page(): void
    {
        $this->seedMarket();
        Currency::create(['code' => 'CHF', 'name' => 'Franc', 'symbol' => 'Fr', 'sort_order' => 4, 'is_active' => true]);

        config(['rates.everyday' => null]);

        $this->get('/en/rates?currency=USD')
            ->assertOk()
            ->assertSee('currency=CHF', false)
            // Every currency is on show, so nothing is unreachable.
            ->assertDontSee('More currencies');
    }

    /** An empty list is the same mistake with a different shape. */
    public function test_an_empty_everyday_config_does_not_hide_every_currency(): void
    {
        $this->seedMarket();

        config(['rates.everyday' => []]);

        $this->get('/en/rates?currency=USD')->assertOk()->assertDontSee('More currencies');
    }
}
