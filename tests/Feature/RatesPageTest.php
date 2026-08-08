<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the intent-driven rebuild of /rates: buy/sell decides the ranking,
 * an optional amount turns each row into a real total, banks and exchange
 * offices are ranked as separate markets, and no filter combination is a
 * dead end.
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

        $bankGroup = collect($response->viewData('groups'))->firstWhere('type', 'bank');
        $this->assertSame(365.0, (float) $bankGroup['best_value'], 'buying should rank by the lowest sell rate');
        $this->assertSame('Cheap bank', $bankGroup['rows'][0]->organization_name);
    }

    public function test_sell_intent_flips_the_ranking_to_the_highest_buy_rate(): void
    {
        $this->seedMarket();

        $response = $this->get('/en/rates?currency=USD&intent=sell');

        $response->assertOk()->assertViewHas('intent', 'sell');

        $bankGroup = collect($response->viewData('groups'))->firstWhere('type', 'bank');
        $this->assertSame(360.0, (float) $bankGroup['best_value'], 'selling should rank by the highest buy rate');
        $this->assertSame('Cheap bank', $bankGroup['rows'][0]->organization_name);
    }

    public function test_banks_and_exchange_offices_are_grouped_and_ranked_independently(): void
    {
        $this->seedMarket();

        $groups = collect($this->get('/en/rates?currency=USD')->viewData('groups'));

        $this->assertEqualsCanonicalizing(['bank', 'exchange'], $groups->pluck('type')->all());
        $this->assertSame(2, $groups->firstWhere('type', 'bank')['count']);
        $this->assertSame(1, $groups->firstWhere('type', 'exchange')['count']);

        // Each market keeps its own best - the exchange office's 388 must not
        // be measured against the banks'.
        $this->assertSame(365.0, (float) $groups->firstWhere('type', 'bank')['best_value']);
        $this->assertSame(388.0, (float) $groups->firstWhere('type', 'exchange')['best_value']);
    }

    public function test_each_group_reports_the_spread_between_its_best_and_worst(): void
    {
        $this->seedMarket();

        $bankGroup = collect($this->get('/en/rates?currency=USD')->viewData('groups'))->firstWhere('type', 'bank');

        // 370.00 - 365.00 = 5.00 AMD per unit between the two banks.
        $this->assertSame(5.0, round((float) $bankGroup['spread_across_market'], 2));
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
