<?php

namespace Tests\Feature;

use App\Enums\RateType;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\CurrencyRateHistory;
use App\Models\Organization;
use App\Services\RateHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The history table is a list of MOVES, not a daily log - RateScraper only
 * appends a row when a rate actually changed. Every assertion here exists
 * because grouping it by date instead would report the days nobody repriced as
 * days with no market.
 */
class RateHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function seedHistory(): Currency
    {
        $usd = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1, 'is_active' => true]);

        $org = Organization::create(['name' => 'Bank', 'slug' => 'bank', 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => true]);

        $rate = CurrencyRate::create(['organization_id' => $org->id, 'currency_id' => $usd->id,
            'rate_type' => RateType::CASH, 'buy_rate' => 366, 'sell_rate' => 370, 'scraped_at' => now()]);

        // Moved eight days ago and again yesterday. Everything between has no
        // rows at all - the rate simply held.
        foreach ([[8, 360.0, 364.0], [1, 366.0, 370.0]] as [$daysAgo, $buy, $sell]) {
            CurrencyRateHistory::create([
                'currency_rate_id' => $rate->id, 'buy_rate' => $buy, 'sell_rate' => $sell,
                'scraped_at' => now()->subDays($daysAgo)->startOfDay(),
            ]);
        }

        return $usd;
    }

    /**
     * The days between two moves are not gaps. A rate set on Monday was still
     * on the board on Wednesday, and a chart that omits Wednesday is wrong.
     */
    public function test_a_rate_that_did_not_move_still_counts_on_the_days_between(): void
    {
        $usd = $this->seedHistory();

        $series = app(RateHistoryService::class)->marketSeries($usd->id, RateType::CASH, 7);

        // Seven days asked for, seven days drawn - none skipped for want of a
        // snapshot.
        $this->assertCount(7, $series);

        // Days six through two carry the eight-day-old value forward rather
        // than vanishing from the chart.
        foreach ([0, 1, 2, 3, 4] as $index) {
            $this->assertSame(360.0, $series[$index]['best_buy'], "day {$index} should carry the last known rate");
        }

        // ...and yesterday's move lands, and holds into today.
        $this->assertSame(366.0, $series[5]['best_buy']);
        $this->assertSame(366.0, $series[6]['best_buy']);
    }

    /** Before our first snapshot we genuinely do not know, so we say nothing. */
    public function test_days_before_the_first_snapshot_are_left_out_rather_than_guessed(): void
    {
        $usd = $this->seedHistory();

        $series = app(RateHistoryService::class)->marketSeries($usd->id, RateType::CASH, 30);
        $dates = array_column($series, 'date');

        $this->assertNotContains(now()->subDays(12)->toDateString(), $dates);
        $this->assertContains(now()->subDays(8)->toDateString(), $dates);
    }

    /**
     * Offering "1 year" over a week of history would draw a chart that is
     * mostly a straight line and entirely a lie.
     */
    public function test_only_ranges_the_data_covers_are_offered(): void
    {
        $this->seedHistory();

        $service = app(RateHistoryService::class);

        $this->assertSame([7], $service->offerableRanges());
        $this->assertNotContains(30, $service->offerableRanges());

        $this->get('/en/rates/history?currency=USD')
            ->assertOk()
            ->assertViewHas('days', 7)
            ->assertSee('Last 7 days')
            ->assertDontSee('Last 30 days')
            // ...and says why, rather than leaving a reader hunting for them.
            ->assertSee('Longer ranges appear as we collect more days.');
    }

    /** A range we cannot draw is not accepted just because it was typed. */
    public function test_an_unavailable_range_falls_back_instead_of_drawing_nothing(): void
    {
        $this->seedHistory();

        $this->get('/en/rates/history?currency=USD&days=365')->assertOk()->assertViewHas('days', 7);
    }

    /** One reading against its own average is always zero, which says nothing. */
    public function test_no_comparison_is_claimed_from_a_single_data_point(): void
    {
        $service = app(RateHistoryService::class);

        $this->assertNull($service->changeAgainstAverage([['best_buy' => 366.0]], 'best_buy'));
        $this->assertNotNull($service->changeAgainstAverage(
            [['best_buy' => 360.0], ['best_buy' => 366.0]],
            'best_buy',
        ));
    }

    public function test_the_page_carries_its_own_seo_description(): void
    {
        $this->seedHistory();

        $this->get('/en/rates/history?currency=USD')
            ->assertOk()
            ->assertSee('USD exchange rate history in Armenia')
            ->assertSee('Daily best buy and sell rates for USD in Armenia', false);
    }
}
