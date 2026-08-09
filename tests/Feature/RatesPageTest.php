<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Organization;
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

    public function test_without_an_amount_the_page_shows_rates_but_no_totals(): void
    {
        $this->seedMarket();

        $response = $this->get('/en/rates?currency=USD');

        $response->assertOk()
            ->assertViewHas('amount', null)
            ->assertDontSee('pay 182,500');

        $this->assertSame(3, $response->viewData('ranked')['count']);
    }

    public function test_an_amount_turns_each_row_into_a_total(): void
    {
        $this->seedMarket();

        // Buying 500 USD from the cheapest bank costs 500 x 365.00 AMD.
        $this->get('/en/rates?currency=USD&amount=500')
            ->assertOk()
            ->assertViewHas('amount', 500.0)
            ->assertSee('182,500');
    }

    public function test_a_non_numeric_amount_is_ignored_rather_than_erroring(): void
    {
        $this->seedMarket();

        foreach (['abc', '-5', '0', '999999999'] as $bad) {
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

        // Buying: cheapest sell rate 365.00, dearest 388.00.
        $ranked = $this->get('/en/rates?currency=USD')->viewData('ranked');

        $this->assertSame(23.0, round((float) $ranked['spread'], 2));
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
        // must still track the rate.
        $rows = collect($this->get('/en/rates?currency=USD&intent=buy&sort=spread&direction=desc')->viewData('ranked')['rows']);

        $this->assertNotSame('Cheap bank', $rows->first()->organization_name, 'precondition: the sort moved it');
        $this->assertSame(1, $rows->firstWhere('organization_name', 'Cheap bank')->rank);
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
        $html = $response->getContent();
        $this->assertSame(2, substr_count($html, 'name="lat" value="40.1792"'), 'every GET form must carry lat');
        $this->assertSame(2, substr_count($html, 'name="lng" value="44.4991"'), 'every GET form must carry lng');
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
}
