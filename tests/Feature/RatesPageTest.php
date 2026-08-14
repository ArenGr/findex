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

/**
 * Covers the intent-driven rebuild of /rates: buy/sell decides the ranking,
 * an optional amount turns each row into a real total, every market shares one
 * ranked table (with tabs to narrow it), and no filter combination is a dead
 * end.
 */
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

    /**
     * Most people arrive to read today's rates, and the Buy/Sell table everyone
     * in this market already knows is the honest answer to that. Totals are a
     * second question, asked by typing an amount.
     */
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

    /**
     * An amount adds a column; it does not swap the table for a different one.
     * The rate pair a visitor was already reading stays exactly where it was,
     * which is the whole point - the page used to reshape itself the moment a
     * number was typed, and it was no longer obvious it was the same table.
     */
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

        // Handing over 500 USD, the exchange office's 384.00 buy rate returns
        // 192,000 AMD and the cheapest bank's 360.00 returns 180,000.
        $this->get('/en/rates?currency=USD&amount=500')
            ->assertOk()
            ->assertViewHas('amount', 500.0)
            ->assertViewHas('intent', 'sell')
            ->assertSee('192,000')
            ->assertSee('180,000');
    }

    /**
     * The other direction is a division, not a multiplication: the amount is
     * denominated in whatever the visitor HAS, so handing over dram asks how
     * much foreign currency it buys. Getting this wrong would multiply two
     * dram figures together and report a number in the millions.
     */
    public function test_handing_over_dram_divides_rather_than_multiplies(): void
    {
        $this->seedMarket();

        // 100,000 AMD at the cheapest sell rate of 365.00 buys 273.97 USD;
        // at the dearest, 388.00, only 257.73.
        $this->get('/en/rates?currency=USD&amount=100000&intent=buy')
            ->assertOk()
            ->assertSee('273.97')
            ->assertSee('257.73')
            // The result is in the currency they wanted, not in dram.
            ->assertSee('You have 100,000 AMD and want USD.');
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

        // Cheapest to buy USD across every market: the bank at 365, not the
        // exchange office at 388.
        $ranked = $response->viewData('ranked');
        $this->assertSame(365.0, (float) $ranked['best_value'], 'buying should rank by the lowest sell rate');
        $this->assertSame('Cheap bank', $ranked['rows'][0]->organization_name);
    }

    public function test_sell_intent_flips_the_ranking_to_the_highest_buy_rate(): void
    {
        $this->seedMarket();

        $response = $this->get('/en/rates?currency=USD&intent=sell');

        $response->assertOk()->assertViewHas('intent', 'sell');

        // Selling USD, the exchange office's 384 beats every bank - which only
        // surfaces because both markets share one ranking.
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

        // Sorted by spread, the cheapest row is no longer row one - the podium
        // must still track the rate. Corner exchange has the tightest spread
        // at 4.00, against Cheap bank's 5.00.
        $rows = collect($this->get('/en/rates?currency=USD&intent=buy&sort=spread')->viewData('ranked')['rows']);

        $this->assertNotSame('Cheap bank', $rows->first()->organization_name, 'precondition: the sort moved it');
        $this->assertSame(1, $rows->firstWhere('organization_name', 'Cheap bank')->rank);
    }

    /**
     * Sorts are named after what the visitor wants rather than after a column,
     * so an unknown or stale one falls back to the default instead of erroring
     * or quietly ordering by nothing.
     */
    public function test_the_named_sorts_are_offered_and_an_unknown_one_falls_back(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD')
            ->assertOk()
            ->assertViewHas('sort', 'best')
            ->assertViewHas('sortOptions', ['best', 'updated', 'spread'])
            ->assertSee('Lowest spread')
            ->assertSee('Recently updated');

        // "Closest" is only meaningful once we have somewhere to measure from.
        $this->get('/en/rates?currency=USD&lat=40.1792&lng=44.4991')
            ->assertOk()
            ->assertViewHas('sort', 'distance')
            ->assertViewHas('sortOptions', ['best', 'updated', 'spread', 'distance']);

        // A bookmarked link from when location sharing was on.
        $this->get('/en/rates?currency=USD&sort=distance')->assertOk()->assertViewHas('sort', 'best');
        $this->get('/en/rates?currency=USD&sort=buy_rate')->assertOk()->assertViewHas('sort', 'best');
    }

    /** Lowest spread first, which is what the option says. */
    public function test_sorting_by_spread_orders_by_the_tightest_gap(): void
    {
        $this->seedMarket();

        $rows = collect($this->get('/en/rates?currency=USD&sort=spread')->viewData('ranked')['rows']);

        // Corner exchange 388-384 = 4.00, Cheap bank 365-360 = 5.00,
        // Pricey bank 370-358 = 12.00.
        $this->assertSame(
            ['Corner exchange', 'Cheap bank', 'Pricey bank'],
            $rows->pluck('organization_name')->all(),
        );
    }

    public function test_rates_a_fraction_apart_do_not_collapse_onto_one_rank(): void
    {
        $usd = $this->seedMarket();
        // Float array keys cast to int in PHP, so 365.50 could silently land on
        // 365.00's rank.
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

        $response = $this->get('/en/rates?currency=USD&lat=40.1792&lng=44.4991')->assertOk();

        // Both GET forms on the page - the intent bar and the bank/city filter -
        // must carry lat/lng, or submitting either one silently drops an active
        // "find nearby". Asserting presence alone is not enough: the intent bar
        // would satisfy it on its own.
        // Derived rather than hard-coded: the count is "every GET form on the
        // page", so adding another one cannot quietly satisfy this by leaving
        // the new form out.
        $html = $response->getContent();
        $forms = substr_count($html, 'method="GET"');

        $this->assertGreaterThanOrEqual(3, $forms, 'precondition: the page has several GET forms');
        $this->assertSame($forms, substr_count($html, 'name="lat" value="40.1792"'), 'every GET form must carry lat');
        $this->assertSame($forms, substr_count($html, 'name="lng" value="44.4991"'), 'every GET form must carry lng');
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
        // A currency the quote form does not accept would otherwise send the
        // visitor to a request they cannot complete.
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

        // Under "All" the list would mix banks and exchange offices, and no
        // single label describes what it contains - so it is not offered.
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

        // Otherwise "Corner exchange" would survive a jump to Banks and every
        // filter combination would return nothing.
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

    /**
     * Disabling is Alpine-bound rather than rendered, because with JS off the
     * buy/sell radios do not self-submit and this button is the only way to
     * apply an intent change.
     */
    public function test_the_submit_button_is_only_disabled_client_side(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD')
            ->assertOk()
            ->assertSee(':disabled="!(Number(amount) > 0)"', false)
            ->assertDontSee('<button type="submit" disabled', false);
    }

    /**
     * Buy and sell are written from the institution's side - the column headed
     * "Sell" is the one a buyer pays - so the calculator asks what the visitor
     * has and what they want instead. Buy/sell survives on the wire and in the
     * table headings, which is what this market publishes.
     */
    public function test_the_calculator_asks_what_you_have_rather_than_buy_or_sell(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD&amount=5000')
            ->assertOk()
            ->assertSee('I have')
            ->assertSee('I want')
            ->assertSee('You have 5,000 USD and want AMD.')
            ->assertDontSee('Buy USD')
            ->assertDontSee('Sell USD');

        $this->get('/en/rates?currency=USD&amount=5000&intent=buy')
            ->assertOk()
            ->assertSee('You have 5,000 AMD and want USD.');
    }

    /**
     * Six banks quoting 368.00 all hold rank 1. Marking one of them looked
     * arbitrary next to five identical numbers, so the winning figure is stated
     * once above the table and every row that holds it is marked.
     */
    public function test_the_best_rate_is_stated_once_above_the_table(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD&intent=buy&amount=100')
            ->assertOk()
            ->assertSee('Current best rate')
            ->assertSee('You receive');
    }

    /**
     * The Simple/Detailed toggle changes exactly one thing - whether the table
     * carries the spread column - and it is offered whether or not an amount
     * has been entered. It used to appear only while calculating, and to swap
     * the whole rate pair in and out, which made it read as a page-level mode
     * rather than a table option.
     */
    public function test_detailed_view_adds_the_spread_column_and_nothing_else(): void
    {
        $this->seedMarket();

        foreach (['', '&amount=100'] as $amount) {
            $this->get('/en/rates?currency=USD'.$amount)
                ->assertOk()
                ->assertViewHas('detailed', false)
                ->assertSee('Detailed')
                ->assertDontSee('Spread');

            $this->get('/en/rates?currency=USD&both=1'.$amount)
                ->assertOk()
                ->assertViewHas('detailed', true)
                ->assertSee('Spread');
        }
    }

    /** The pair is on screen in every one of those four states. */
    public function test_the_rate_pair_is_always_on_screen(): void
    {
        $this->seedMarket();

        foreach (['', '&amount=100', '&both=1', '&both=1&amount=100'] as $extra) {
            $this->get('/en/rates?currency=USD'.$extra)
                ->assertOk()
                ->assertSee('>Buy', false)
                ->assertSee('>Sell', false);
        }
    }

    /**
     * Three banks quoting the same winning rate all get a star, and three
     * identical stars with nothing explaining them read as a fault. The label
     * accounts for the repetition wherever the star is read from - tooltip,
     * hover, or screen reader.
     */
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

    /**
     * Staleness used to be amber text and nothing else, which reaches neither a
     * screen reader nor anyone who cannot separate the amber from the grey. The
     * warning carries the same meaning in words.
     */
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

    /**
     * "Only rates worth trusting". Day and week rather than the minutes the
     * brief suggests: the scrapers run daily, so a minutes option would always
     * empty the table and read as a fault rather than as a filter.
     */
    public function test_the_freshness_filter_drops_rates_older_than_the_window(): void
    {
        $usd = $this->seedMarket();
        CurrencyRate::query()
            ->whereHas('organization', fn ($query) => $query->where('type', 'exchange'))
            ->update(['scraped_at' => now()->subWeeks(2)]);

        $this->assertSame(3, $this->get('/en/rates?currency=USD')->viewData('ranked')['count']);
        $this->assertSame(2, $this->get('/en/rates?currency=USD&fresh=day')->viewData('ranked')['count']);
        $this->assertSame(2, $this->get('/en/rates?currency=USD&fresh=week')->viewData('ranked')['count']);

        // An unknown window is ignored rather than emptying the table.
        $this->assertSame(3, $this->get('/en/rates?currency=USD&fresh=decade')->viewData('ranked')['count']);
    }

    /** It counts towards the badge, or a narrowed table looks like a full one. */
    public function test_the_freshness_filter_counts_as_an_active_filter(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD')->assertOk()->assertDontSee('Filters (');
        $this->get('/en/rates?currency=USD&fresh=day')->assertOk()->assertSee('Filters (1)');
    }

    /**
     * A freshness-filtered page and an unfiltered one must not share a cache
     * entry - they are different result sets under the same currency and type.
     */
    public function test_the_freshness_filter_is_part_of_the_cache_key(): void
    {
        $this->seedMarket();
        CurrencyRate::query()
            ->whereHas('organization', fn ($query) => $query->where('type', 'exchange'))
            ->update(['scraped_at' => now()->subWeeks(2)]);

        // Warm the unfiltered entry first, so a shared key would serve three.
        $this->get('/en/rates?currency=USD')->assertOk();

        $this->assertSame(2, $this->get('/en/rates?currency=USD&fresh=day')->viewData('ranked')['count']);
    }

    /**
     * When a rate last moved, as opposed to when it was last looked at.
     * "Checked 22 hours ago" is true of every bank at once; "unchanged for a
     * week" is what tells them apart.
     */
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

    /**
     * The saving beside the results count measures against the WORST rate on
     * the page, which flatters us - nobody would have picked it. This is the
     * honest version, and it must reconcile with the average card printed
     * above it or the page is doing arithmetic the reader cannot check.
     */
    public function test_the_best_result_is_compared_against_the_market_average(): void
    {
        $this->seedMarket();

        // Handing over 100 USD. Buy rates 360, 358 and 384 average 367.33, and
        // the best is 384.00, so the winner is worth 100 x 16.67 = 1,667 AMD
        // more than average.
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

    /**
     * Everything /rates already knows travels to the negotiation form. Asking
     * for the currency, amount, city and direction a second time is the surest
     * way to lose someone between two pages.
     */
    public function test_the_negotiation_link_carries_the_whole_exchange_context(): void
    {
        $this->seedMarket();
        Branch::create(['organization_id' => Organization::firstOrFail()->id, 'name' => 'B', 'city' => 'Yerevan', 'is_active' => true]);

        $html = $this->get('/en/rates?currency=USD&amount=5000&city=Yerevan&intent=buy')
            ->assertOk()
            ->getContent();

        // intent=buy means they hold dram and want the currency, which is the
        // organization's sell side.
        $this->assertStringContainsString('currency=USD', $html);
        $this->assertMatchesRegularExpression('/exchange\?[^"]*amount=5000/', $html);
        $this->assertMatchesRegularExpression('/exchange\?[^"]*city=Yerevan/', $html);
        $this->assertMatchesRegularExpression('/exchange\?[^"]*rate_field=sell_rate/', $html);
    }

    /**
     * Below the minimum the amount still travels. The form states its own
     * minimum; blanking the number they typed teaches them nothing.
     */
    public function test_an_amount_below_the_quote_minimum_still_travels(): void
    {
        $this->seedMarket();

        $html = $this->get('/en/rates?currency=USD&amount=50')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/exchange\?[^"]*amount=50/', $html);
    }

    /**
     * The map is never the default and its library is never loaded until it is
     * asked for: most visits are here to read a table of numbers, which a map
     * is slower at.
     */
    public function test_the_list_is_the_default_and_the_map_is_opt_in(): void
    {
        $this->seedMarket();

        // Without a mapped branch the map view shows its empty state instead of
        // a canvas, which is a different assertion (see below).
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
            // The map replaces the table rather than joining it: two renderings
            // of the same rows is the duplication this page keeps shedding.
            ->assertDontSee('<table', false);

        $this->get('/en/rates?currency=USD&view=nonsense')->assertOk()->assertViewHas('viewMode', 'list');
    }

    /**
     * A rate belongs to an organization and an address belongs to a branch, so
     * one row can put several pins on the map.
     */
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

    /**
     * Sorting and the spread column both describe the table. With no table on
     * screen they would be controls that visibly do nothing.
     */
    public function test_table_only_controls_stand_down_in_map_view(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD')->assertOk()->assertSee('Lowest spread')->assertSee('Detailed');
        $this->get('/en/rates?currency=USD&view=map')->assertOk()->assertDontSee('Lowest spread')->assertDontSee('Detailed');
    }

    /**
     * On a phone the filter panel is a bottom sheet, and it confirms with the
     * result rather than a bare "Done" - the table is behind the sheet, so the
     * count is the only way to see what the choices did before closing it.
     */
    public function test_the_filter_sheet_confirms_with_the_number_of_results(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD')->assertOk()->assertSee('Show 3 rates');
        $this->get('/en/rates?currency=USD&org_type=exchange')->assertOk()->assertSee('Show 1 rate');
    }

    /**
     * Freshness moves out of the line under the name and into a column of its
     * own, so it can be read down the list rather than row by row.
     */
    public function test_the_table_carries_an_updated_column(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD')->assertOk()->assertSee('Updated');
    }

    /**
     * Three figures above the table, so the answer to "what is the best rate
     * here" does not require reading fourteen rows twice. The seeded market
     * quotes 360/365, 358/370 and 384/388, so the visitor's best buy is the
     * highest buy and their best sell is the lowest sell.
     *
     * The average is taken over the column the visitor is transacting on -
     * by default the buy side, (360 + 358 + 384) / 3 - rather than over the
     * midpoint between buy and sell. A midpoint average is a number nobody
     * quotes, and comparing a real total against it produced a saving that did
     * not reconcile with the card printed above it.
     */
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

    /**
     * The cards stay put when an amount is entered, and the best-rate band
     * joins them. Swapping one for the other was the single biggest reason
     * typing a number felt like landing on a different page.
     */
    public function test_the_summary_cards_survive_a_calculation(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD&amount=100')
            ->assertOk()
            ->assertSee('Market average')
            ->assertSee('Best buy rate')
            ->assertSee('Current best rate');
    }

    /**
     * It is the official reference rate, not a venue - offering it beside Cash
     * and Card sent visitors to rows they could not act on.
     */
    public function test_the_central_bank_rate_is_a_reference_line_not_a_filter(): void
    {
        $usd = $this->seedMarket();
        $cba = $this->organization('central-bank');
        $this->rate($cba, $usd, 366.17, 366.17, 'central_bank');

        $response = $this->get('/en/rates?currency=USD');

        $response->assertOk()
            ->assertSee('Central Bank official rate: 366.17')
            ->assertDontSee('>Central Bank<', false);

        $this->assertNotContains('central_bank', $response->viewData('availableTypes')->all());
    }

    /**
     * Every alert route is behind auth. A guest who filled in the modal would
     * be bounced to login and lose it, so they are asked to sign in first.
     */
    public function test_the_alert_modal_asks_a_guest_to_sign_in_before_the_form(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD')
            ->assertOk()
            ->assertSee('Rate alerts are tied to your account', false)
            ->assertDontSee('name="threshold"', false);
    }

    /**
     * The point of the modal over the redirect: /rates already knows the
     * currency, the transaction type, the buy/sell direction and the going
     * rate, so the form arrives answered rather than blank.
     */
    public function test_the_alert_modal_is_prefilled_from_what_is_on_screen(): void
    {
        $this->seedMarket();

        $html = $this->actingAs(User::factory()->create())
            ->get('/en/rates?currency=USD&intent=buy&type=cash')
            ->assertOk()
            ->assertSee('name="threshold"', false)
            ->getContent();

        // Buying ranks on sell_rate, and the visitor wants to be told when it
        // drops - so the alert watches that field, below the current best.
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
     * Decoded rather than string-matched: what matters is the values reaching
     * the form, not how Blade happened to escape them into the attribute.
     *
     * @return array<string, string>
     */
    private function alertPrefill(string $html): array
    {
        $this->assertMatchesRegularExpression("/rate-alert-open', \{ detail: JSON\.parse\('/", $html);

        preg_match("/rate-alert-open', \{ detail: JSON\.parse\('(.+?)'\)/", $html, $matches);

        return json_decode(json_decode('"'.$matches[1].'"'), true)['form'];
    }

    /**
     * An alert set from /rates is a side errand - the visitor was comparing
     * rates and should land back on the same filtered view, not on the alert
     * management page with their filters gone.
     */
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

    /**
     * store() rejects an unconnected channel server-side, and the modal - unlike
     * the alerts page - has nowhere to show connection state or a connect
     * button, so offering one that will bounce is a dead end.
     */
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

    /**
     * Totals were formatted to whole dram, which is right at 368,000 and wrong
     * at 4.60: it rendered as "5", and so did the office next to it quoting
     * 4.75 - so the table stopped telling two rows apart at exactly the amounts
     * where the difference is easiest to read.
     */
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
            // The saving line has its own floor, which was written for dram
            // totals in the thousands and swallowed this whole.
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

    /**
     * "Current best rate" sitting above "1 day ago", with "these rates are more
     * than a day old" below it, is a contradiction the visitor resolves by
     * trusting the page less. The claim drops to what is actually true.
     */
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

    /**
     * "Buy USD" does not say buy from whom, or with what. Both sides of the
     * movement are named, and both readings are in the markup because the
     * radios do not reload the page in the plain table.
     */
    public function test_the_direction_is_spelled_out_as_a_sentence(): void
    {
        $this->seedMarket();

        // Before anything is typed the sentence still names the pair, with the
        // field's own placeholder standing in for the amount.
        $this->get('/en/rates?currency=USD')
            ->assertOk()
            ->assertSee('and want AMD.');

        $this->get('/en/rates?currency=USD&intent=buy')
            ->assertOk()
            ->assertSee('and want USD.');
    }

    /** The spread restated as something that happens to the visitor. */
    public function test_the_spread_is_framed_as_what_the_visitor_keeps(): void
    {
        $this->seedMarket();

        // Handing over 100 USD: 384.00 at the exchange office against 358.00
        // at the worst bank is 2,600 AMD.
        $this->get('/en/rates?currency=USD&amount=100&intent=sell')
            ->assertOk()
            ->assertSee('Choosing the best rate here earns you 2,600 AMD more.');

        // Handing over 100,000 AMD: 100000/365 - 100000/388 = 16.24 USD. The
        // saving follows the units of what you receive, so it is quoted in USD.
        $this->get('/en/rates?currency=USD&amount=100000&intent=buy')
            ->assertOk()
            ->assertSee('earns you 16.24 USD more.');
    }

    /** Round numbers, one tap, no keyboard - and shareable, since they are links. */
    public function test_the_quick_amounts_are_links_that_carry_the_current_filters(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD&intent=sell&type=cash')
            ->assertOk()
            ->assertSee('amount=500', false)
            ->assertSee('amount=5000', false);
    }

    private function branch(Organization $org, string $name, float $lat, float $lng, ?string $city = 'Yerevan'): Branch
    {
        return Branch::create([
            'organization_id' => $org->id, 'name' => $name, 'city' => $city,
            'latitude' => $lat, 'longitude' => $lng, 'is_active' => true,
        ]);
    }

    /**
     * A rate belongs to an organization, but you walk to a branch - and most
     * organizations here have more than one. Guessing sends people across town,
     * so with nothing to disambiguate on, only a single-branch organization
     * gets a link.
     */
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
            // The two-branch organization is on the page, but with nothing to
            // choose between Kentron and Vagharshapat it gets no link.
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

    /**
     * Once location is shared the branch is known, so every row with one gets a
     * link - and it points at the nearest, not the first the database returns.
     */
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

    /**
     * A page called "All Exchange Rates" that shows no rates until you scroll
     * is answering the wrong question first. Currency is the only control with
     * no sensible default, so it is the only one on sight; the rest sit behind
     * a button that counts what has moved off its default, which is the only
     * sign on the page that the table has been narrowed.
     */
    public function test_the_filter_button_counts_what_is_narrowing_the_table(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD')
            ->assertOk()
            ->assertSee('Filters')
            // No count when nothing has moved off its default.
            ->assertDontSee('Filters (');

        $this->get('/en/rates?currency=USD&org_type=exchange&organization=corner-exchange')
            ->assertOk()
            ->assertSee('Filters (2)');
    }

    /** Open only when there is already an amount to show a result for. */
    public function test_the_calculator_starts_collapsed_and_opens_with_an_amount(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD')
            ->assertOk()
            ->assertSee('How much are you exchanging?')
            ->assertSee('x-data="{ open: false }"', false);

        $this->get('/en/rates?currency=USD&amount=500')
            ->assertOk()
            ->assertSee('x-data="{ open: true }"', false);
    }
}
