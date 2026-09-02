<?php

namespace Tests\Feature;

use App\Enums\MortgageRateType;
use App\Models\MortgageOffer;
use App\Models\Organization;
use App\Services\MortgageComparison;
use App\Support\Mortgage\MortgageScenario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * The ranking rules of the mortgage comparator: cohort isolation, APR-first
 * ranking with a nominal fallback, eligibility gating, data-quality gating,
 * and the tie-break order.
 */
class MortgageComparisonTest extends TestCase
{
    use RefreshDatabase;

    private int $orgSeq = 0;

    private function bank(): Organization
    {
        $this->orgSeq++;

        return Organization::create([
            'name' => "Bank {$this->orgSeq}",
            'slug' => "bank-{$this->orgSeq}",
            'type' => 'bank',
            'country_code' => 'AM',
            'is_active' => true,
        ]);
    }

    private function offer(array $attributes): MortgageOffer
    {
        return MortgageOffer::create(array_merge([
            'organization_id' => $this->bank()->id,
            'currency' => 'AMD',
            'rate_type' => MortgageRateType::FIXED->value,
            'category' => 'secondary_market',
            'interest_rate_min' => 13,
            'interest_rate_max' => 13,
            'scraped_at' => now(),
        ], $attributes));
    }

    private function rank(Collection $offers, ?MortgageScenario $scenario = null): array
    {
        return app(MortgageComparison::class)->rank(
            $offers,
            $scenario ?? MortgageScenario::standard(),
        );
    }

    public function test_it_ranks_by_effective_apr_ascending_and_prefers_apr_over_nominal(): void
    {
        $pricey = $this->offer(['apr_min' => 15.5, 'interest_rate_min' => 14]);
        $cheap = $this->offer(['apr_min' => 12.0, 'interest_rate_min' => 13]);

        $result = $this->rank(collect([$pricey, $cheap]));

        $this->assertCount(2, $result['ranked']);
        $this->assertSame($cheap->id, $result['ranked'][0]->offer->id);
        $this->assertSame('apr', $result['ranked'][0]->rateBasis);
        $this->assertEqualsWithDelta(12.0, $result['ranked'][0]->effectiveRatePercent, 0.001);
    }

    public function test_a_nominal_only_offer_falls_back_and_is_badged(): void
    {
        $offer = $this->offer(['apr_min' => null, 'interest_rate_min' => 11]);

        $row = $this->rank(collect([$offer]))['ranked'][0];

        $this->assertSame('nominal', $row->rateBasis);
        $this->assertTrue($row->hasBadge('rate_only'));
    }

    public function test_offers_outside_the_scenario_cohort_are_excluded(): void
    {
        $amdSecondary = $this->offer(['currency' => 'AMD', 'category' => 'secondary_market', 'apr_min' => 13]);
        $usd = $this->offer(['currency' => 'USD', 'category' => 'secondary_market', 'apr_min' => 9]);
        $primary = $this->offer(['currency' => 'AMD', 'category' => 'primary_market', 'apr_min' => 8]);

        $result = $this->rank(collect([$amdSecondary, $usd, $primary]));

        $this->assertCount(1, $result['ranked']);
        $this->assertSame($amdSecondary->id, $result['ranked'][0]->offer->id);
    }

    public function test_an_offer_the_borrower_is_ineligible_for_is_dropped(): void
    {
        // Scenario: 30M loan, 30% down. This offer demands 40% down.
        $tooStrict = $this->offer(['apr_min' => 10, 'min_down_payment_percent' => 40]);
        $ok = $this->offer(['apr_min' => 13, 'min_down_payment_percent' => 10]);

        $result = $this->rank(collect([$tooStrict, $ok]));

        $this->assertCount(1, $result['ranked']);
        $this->assertSame($ok->id, $result['ranked'][0]->offer->id);
    }

    public function test_an_offer_with_no_rate_goes_to_the_incomplete_bucket(): void
    {
        // A "terms on request" offer - the DB requires a nominal rate, so
        // this is an in-memory row (e.g. one a parser couldn't fully fill).
        // The ranker must still shelve it rather than crash or rank it blind.
        $noRate = (new MortgageOffer)->forceFill([
            'organization_id' => $this->bank()->id,
            'currency' => 'AMD',
            'rate_type' => MortgageRateType::FIXED->value,
            'category' => 'secondary_market',
            'interest_rate_min' => null,
            'interest_rate_max' => null,
            'apr_min' => null,
            'scraped_at' => now(),
        ]);

        $result = $this->rank(collect([$noRate]));

        $this->assertCount(0, $result['ranked']);
        $this->assertCount(1, $result['incomplete']);
    }

    public function test_an_expired_promotion_is_not_ranked(): void
    {
        $expired = $this->offer(['apr_min' => 9, 'promo_ends_at' => now()->subDay()]);
        $live = $this->offer(['apr_min' => 13, 'promo_ends_at' => now()->addMonth()]);

        $result = $this->rank(collect([$expired, $live]));

        $this->assertCount(1, $result['ranked']);
        $this->assertSame($live->id, $result['ranked'][0]->offer->id);
        $this->assertTrue($result['ranked'][0]->hasBadge('promo'));
    }

    public function test_ties_break_on_a_lower_down_payment(): void
    {
        $higherDown = $this->offer(['apr_min' => 12, 'min_down_payment_percent' => 30]);
        $lowerDown = $this->offer(['apr_min' => 12, 'min_down_payment_percent' => 10]);

        $result = $this->rank(collect([$higherDown, $lowerDown]));

        $this->assertSame($lowerDown->id, $result['ranked'][0]->offer->id);
    }

    public function test_it_computes_a_sane_monthly_payment(): void
    {
        // 30,000,000 AMD at 12% over 240 months annuity ≈ 330,000/month.
        $offer = $this->offer(['apr_min' => 12]);

        $row = $this->rank(collect([$offer]))['ranked'][0];

        $this->assertEqualsWithDelta(330_000, $row->monthlyPayment, 5_000);
        $this->assertEqualsWithDelta($row->monthlyPayment * 240, $row->totalCost, 1);
    }
}
