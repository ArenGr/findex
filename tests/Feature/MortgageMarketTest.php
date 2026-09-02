<?php

namespace Tests\Feature;

use App\Enums\MortgageRateType;
use App\Models\MortgageOffer;
use App\Models\Organization;
use App\Services\MortgageMarket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The benchmark tier: average / cheapest rates that give a single offer its
 * market context. Expired promos and rate-less offers are left out of the
 * average, since they aren't part of "today's" market.
 */
class MortgageMarketTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function offer(array $attributes): MortgageOffer
    {
        $this->seq++;
        $org = Organization::create([
            'name' => "Bank {$this->seq}", 'slug' => "bank-{$this->seq}",
            'type' => 'bank', 'country_code' => 'AM', 'is_active' => true,
        ]);

        return MortgageOffer::create(array_merge([
            'organization_id' => $org->id,
            'currency' => 'AMD',
            'rate_type' => MortgageRateType::FIXED->value,
            'category' => 'secondary_market',
            'interest_rate_min' => 13,
            'interest_rate_max' => 13,
            'scraped_at' => now(),
        ], $attributes));
    }

    private function market(): MortgageMarket
    {
        return app(MortgageMarket::class);
    }

    public function test_benchmark_averages_only_current_rated_offers(): void
    {
        $offers = collect([
            $this->offer(['apr_min' => 10]),
            $this->offer(['apr_min' => 12]),
            $this->offer(['apr_min' => 8, 'promo_ends_at' => now()->subDay()]),   // expired promo
            $this->offer(['apr_min' => null, 'interest_rate_min' => 0.0]),        // no usable rate
        ]);

        $benchmark = $this->market()->benchmark($offers);

        $this->assertSame(2, $benchmark['count']);
        $this->assertSame(11.0, $benchmark['avg_rate']);
        $this->assertSame(10.0, $benchmark['min_rate']);
        $this->assertNotNull($benchmark['as_of']);
    }

    public function test_benchmark_falls_back_to_nominal_when_no_apr(): void
    {
        $offers = collect([$this->offer(['apr_min' => null, 'interest_rate_min' => 13.5])]);

        $this->assertSame(13.5, $this->market()->benchmark($offers)['avg_rate']);
    }

    public function test_overview_groups_by_currency_and_category_amd_first(): void
    {
        $offers = collect([
            $this->offer(['currency' => 'USD', 'category' => 'secondary_market', 'apr_min' => 9]),
            $this->offer(['currency' => 'AMD', 'category' => 'secondary_market', 'apr_min' => 13]),
            $this->offer(['currency' => 'AMD', 'category' => 'secondary_market', 'apr_min' => 15]),
            $this->offer(['currency' => 'AMD', 'category' => 'primary_market', 'apr_min' => 11]),
        ]);

        $overview = $this->market()->overview($offers);

        // AMD rows come before USD; within AMD, cheaper average first.
        $this->assertSame('AMD', $overview[0]['currency']);
        $this->assertSame('primary_market', $overview[0]['category']);
        $this->assertSame(11.0, $overview[0]['avg_rate']);

        $amdSecondary = collect($overview)->firstWhere(fn ($r) => $r['currency'] === 'AMD' && $r['category'] === 'secondary_market');
        $this->assertSame(2, $amdSecondary['count']);
        $this->assertSame(14.0, $amdSecondary['avg_rate']); // (13+15)/2
        $this->assertSame(13.0, $amdSecondary['min_rate']);

        $this->assertSame('USD', $overview[array_key_last($overview)]['currency']);
    }
}
