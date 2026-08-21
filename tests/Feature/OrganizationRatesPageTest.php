<?php

namespace Tests\Feature;

use App\Enums\RateType;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\CurrencyRateHistory;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The rates half of an organization page. These pages existed but showed no
 * rates at all - the one thing someone searching "<bank> exchange rates" came
 * for, and the reason they work as an entry point rather than only as somewhere
 * /rates sends you.
 */
class OrganizationRatesPageTest extends TestCase
{
    use RefreshDatabase;

    private function currency(string $code = 'USD', int $order = 1): Currency
    {
        return Currency::firstOrCreate(
            ['code' => $code],
            ['name' => $code.' name', 'symbol' => '$', 'sort_order' => $order, 'is_active' => true],
        );
    }

    private function organization(string $slug, string $type = 'bank', array $overrides = []): Organization
    {
        return Organization::create(array_merge([
            'name' => ucfirst($slug), 'slug' => $slug, 'type' => $type,
            'country_code' => 'AM', 'is_active' => true,
        ], $overrides));
    }

    private function rate(Organization $org, Currency $currency, float $buy, float $sell, string $type = 'cash'): CurrencyRate
    {
        return CurrencyRate::create([
            'organization_id' => $org->id, 'currency_id' => $currency->id, 'rate_type' => $type,
            'buy_rate' => $buy, 'sell_rate' => $sell, 'scraped_at' => now(),
        ]);
    }

    public function test_a_bank_page_lists_its_rates_grouped_by_transaction_type(): void
    {
        $usd = $this->currency();
        $bank = $this->organization('acba');

        $this->rate($bank, $usd, 363.0, 367.0, RateType::CASH->value);
        $this->rate($bank, $usd, 362.0, 368.0, RateType::CARD->value);

        $this->get('/en/organizations/acba')
            ->assertOk()
            ->assertSee('Exchange rates')
            // Cash and card are different products at different prices; a flat
            // list invites reading one as the other.
            ->assertSee('Cash')
            ->assertSee('Card')
            ->assertSee('363.00')
            ->assertSee('368.00');
    }

    /**
     * The groups are alternatives, not a sequence - the cash rate and the card
     * rate for one currency answer different questions - so they share a table
     * and a set of tabs rather than stacking four of them down the page.
     */
    public function test_each_transaction_type_gets_a_tab_and_only_the_first_is_shown(): void
    {
        $usd = $this->currency();
        $bank = $this->organization('acba');

        $this->rate($bank, $usd, 363.0, 367.0, RateType::CASH->value);
        $this->rate($bank, $usd, 362.0, 368.0, RateType::CARD->value);

        $html = $this->get('/en/organizations/acba')->assertOk()->getContent();

        $this->assertStringContainsString('id="rate-tab-cash"', $html);
        $this->assertStringContainsString('id="rate-tab-card"', $html);

        // The first panel paints immediately; the rest stay hidden until a tab
        // is chosen. Without the cloak they all flash on screen at load.
        $this->assertMatchesRegularExpression(
            '/id="rate-panel-cash"(?![^>]*x-cloak)/',
            preg_replace('/\s+/', ' ', $html),
            'The first panel should not be cloaked.',
        );
        $this->assertMatchesRegularExpression(
            '/x-cloak[^>]*id="rate-panel-card"|id="rate-panel-card"[^>]*x-cloak/',
            preg_replace('/\s+/', ' ', $html),
            'Every panel after the first should be cloaked.',
        );
    }

    /**
     * A bank quoting one kind of rate needs no tabs to choose between - it
     * keeps the plain heading it always had.
     */
    public function test_a_single_transaction_type_is_shown_without_tabs(): void
    {
        $usd = $this->currency();
        $bank = $this->organization('idbank');

        $this->rate($bank, $usd, 363.0, 367.0, RateType::CASH->value);

        $html = $this->get('/en/organizations/idbank')->assertOk()->getContent();

        $this->assertStringNotContainsString('role="tab"', $html);
        $this->assertStringContainsString('Cash', $html);
    }

    /**
     * Whether this organization holds the best rate in the country is the one
     * fact a visitor cannot work out from this page alone, so it is the one
     * thing worth marking.
     */
    public function test_a_rate_that_leads_the_market_is_starred(): void
    {
        $usd = $this->currency();
        $winner = $this->organization('winner');
        $loser = $this->organization('loser');

        // The winner buys highest and sells lowest, so it leads on both sides.
        $this->rate($winner, $usd, 384.0, 365.0);
        $this->rate($loser, $usd, 358.0, 370.0);

        $this->get('/en/organizations/winner')->assertOk()->assertSee('Best in Armenia');
        $this->get('/en/organizations/loser')->assertOk()->assertDontSee('Best in Armenia');
    }

    /** Each row goes back out to the comparison it cannot make on its own. */
    public function test_each_row_links_to_the_full_comparison_for_that_currency(): void
    {
        $usd = $this->currency();
        $this->rate($this->organization('acba'), $usd, 363.0, 367.0);

        $this->get('/en/organizations/acba')
            ->assertOk()
            ->assertSee('See all USD rates')
            ->assertSee('currency=USD', false);
    }

    /**
     * Only exchange offices negotiate walk-in cash, and only once they are
     * reachable - the same rule the fan-out job applies, so the page cannot
     * offer something the job would silently drop.
     */
    public function test_only_a_reachable_exchange_office_offers_to_negotiate(): void
    {
        $usd = $this->currency();

        $reachable = $this->organization('reachable', 'exchange', ['telegram_chat_id' => '123']);
        $unreachable = $this->organization('unreachable', 'exchange');
        $bank = $this->organization('a-bank');

        foreach ([$reachable, $unreachable, $bank] as $org) {
            $this->rate($org, $usd, 363.0, 367.0);
        }

        // The CTA opens the same modal /rates uses, so it is asserted on the
        // dialog it opens as well as on its label.
        $this->get('/en/organizations/reachable')->assertOk()
            ->assertSee('Get a better rate')
            ->assertSee('better-rate-open', false);
        $this->get('/en/organizations/unreachable')->assertOk()
            ->assertDontSee('Get a better rate')
            ->assertDontSee('better-rate-open', false);
        $this->get('/en/organizations/a-bank')->assertOk()
            ->assertDontSee('Get a better rate')
            ->assertDontSee('better-rate-open', false);
    }

    /** Travel agencies and insurers publish no rates; the section stays away. */
    public function test_an_organization_without_rates_has_no_rates_section(): void
    {
        $this->organization('an-insurer', 'insurance');

        // Asserted on the heading, not on the words: the footer now links to
        // /rates with the same label, and "the phrase appears nowhere" was
        // never what this test meant.
        $this->get('/en/organizations/an-insurer')
            ->assertOk()
            ->assertDontSee('<h2 class="mt-12 font-heading text-xl font-semibold break-words text-ink">Exchange rates', false);
    }

    /**
     * The description is what a search result shows. Only claimed when there
     * are rates behind it - an empty page promising live rates is worse than
     * one that promises nothing.
     */
    public function test_the_meta_description_names_the_rates_only_when_there_are_some(): void
    {
        $usd = $this->currency();
        $this->rate($this->organization('acba'), $usd, 363.0, 367.0);
        $this->organization('quiet-bank');

        $this->get('/en/organizations/acba')
            ->assertOk()
            ->assertSee('Acba exchange rates in Armenia', false);

        $this->get('/en/organizations/quiet-bank')
            ->assertOk()
            ->assertDontSee('exchange rates in Armenia', false);
    }

    /**
     * The page had the branches all along and only used them as a dropdown in
     * the review form. Hours are three states: open, shut, and never recorded -
     * and calling the third "closed" would send someone away from an open door.
     */
    public function test_branches_are_listed_with_their_hours(): void
    {
        $usd = $this->currency();
        $bank = $this->organization('acba');
        $this->rate($bank, $usd, 363.0, 367.0);

        Branch::create([
            'organization_id' => $bank->id, 'name' => 'Kentron', 'address' => 'Amiryan St 2',
            'city' => 'Yerevan', 'is_active' => true,
            'opening_hours' => ['mon' => ['09:30', '17:30'], 'sun' => null],
        ]);
        Branch::create([
            'organization_id' => $bank->id, 'name' => 'Gyumri', 'city' => 'Gyumri', 'is_active' => true,
        ]);

        // Monday 10:00 in Yerevan.
        $this->travelTo('2026-08-17 06:00:00');

        $this->get('/en/organizations/acba')
            ->assertOk()
            ->assertSee('Branches')
            ->assertSee('Kentron')
            ->assertSee('Amiryan St 2')
            ->assertSee('Open')
            ->assertSee('09:30')
            // The branch with nothing on file says so rather than claiming shut.
            ->assertSee('Hours not published');

        // Monday 18:00 Yerevan - after 17:30.
        $this->travelTo('2026-08-17 14:00:00');
        $this->get('/en/organizations/acba')->assertOk()->assertSee('Closed');
    }

    /**
     * A trend for this organization's headline currency - enough to see whether
     * it moves its rates or sits still.
     *
     * The guard is on the number of DAYS drawn, not the number of snapshots: a
     * rate recorded once three days ago carries forward and gives four days of
     * flat line, which is a true and useful chart. What is not a chart is a
     * single day, which is a dot.
     */
    public function test_a_rate_trend_appears_once_there_is_more_than_one_day_to_draw(): void
    {
        $usd = $this->currency();
        $bank = $this->organization('acba');
        $rate = $this->rate($bank, $usd, 363.0, 367.0);

        // Recorded today and only today: one day, nothing to draw a line along.
        CurrencyRateHistory::create([
            'currency_rate_id' => $rate->id, 'buy_rate' => 363.0, 'sell_rate' => 367.0,
            'scraped_at' => now(),
        ]);

        $this->get('/en/organizations/acba')->assertOk()->assertDontSee('History for USD');

        // An older snapshot carries forward and the days in between fill in.
        CurrencyRateHistory::create([
            'currency_rate_id' => $rate->id, 'buy_rate' => 360.0, 'sell_rate' => 364.0,
            'scraped_at' => now()->subDays(3),
        ]);

        $this->get('/en/organizations/acba')
            ->assertOk()
            ->assertSee('History for USD')
            ->assertSee('See rate history');
    }

    /** Checked and changed are different facts, and the second is the useful one. */
    public function test_a_row_says_when_its_rate_last_moved(): void
    {
        $usd = $this->currency();
        $rate = $this->rate($this->organization('acba'), $usd, 363.0, 367.0);

        CurrencyRateHistory::create([
            'currency_rate_id' => $rate->id,
            'buy_rate' => 360.0, 'sell_rate' => 364.0,
            'scraped_at' => now()->subWeek(),
        ]);

        $this->get('/en/organizations/acba')
            ->assertOk()
            ->assertSee('Rate unchanged since 1 week ago');
    }
}
