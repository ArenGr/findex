<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\CurrencyRate;
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

    /** An amount swaps in the other table: one rate column and a total. */
    public function test_an_amount_switches_to_the_calculated_table(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD&amount=500&intent=buy')
            ->assertOk()
            ->assertSee('Total you pay')
            ->assertSee('Current best rate')
            ->assertSee('Rate per 1 USD');
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
        $rows = collect($this->get('/en/rates?currency=USD&intent=buy&sort=buy_rate&direction=desc')->viewData('ranked')['rows']);

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
     * The institution-side pair is the single biggest trap on this page: buying
     * USD, the number you pay sits under a column headed "Sell". The visible
     * columns are a neutral rate plus a total that names the direction, and the
     * pair only appears when asked for.
     */
    public function test_only_the_visitor_side_rate_is_shown_by_default(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD&intent=buy&amount=100')
            ->assertOk()
            ->assertSee('Rate per 1 USD')
            ->assertSee('Total you pay')
            ->assertDontSee('>Buy<', false)
            ->assertDontSee('>Sell<', false);

        $this->get('/en/rates?currency=USD&intent=sell&amount=100')
            ->assertOk()
            ->assertSee('Total you get');
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
            ->assertSee('Total you pay');
    }

    public function test_both_rates_can_be_revealed_on_request(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD&both=1&amount=100')
            ->assertOk()
            ->assertViewHas('showBothRates', true)
            ->assertSee('Hide buy and sell rates');
    }

    /** Jargon, and unusable once the savings column it supported was removed. */
    public function test_the_spread_column_is_gone(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD')->assertOk()->assertDontSee('Spread');
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

        $this->get('/en/rates?currency=USD&amount=500&intent=buy')
            ->assertOk()
            ->assertSee('182,500')
            ->assertDontSee('182,500.00');
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
    public function test_both_directions_spell_out_which_way_the_money_moves(): void
    {
        $this->seedMarket();

        $this->get('/en/rates?currency=USD')
            ->assertOk()
            ->assertSee('You pay AMD and receive USD.')
            ->assertSee('You hand over USD and receive AMD.');
    }

    /** The spread restated as something that happens to the visitor. */
    public function test_the_spread_is_framed_as_what_the_visitor_keeps(): void
    {
        $this->seedMarket();

        // Buying 100 USD: 365.00 at the cheapest bank against 388.00 at the
        // exchange office is 2,300 AMD.
        $this->get('/en/rates?currency=USD&amount=100&intent=buy')
            ->assertOk()
            ->assertSee('Choosing the best rate here saves you 2,300 AMD');

        $this->get('/en/rates?currency=USD&amount=100&intent=sell')
            ->assertOk()
            ->assertSee('more');
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
