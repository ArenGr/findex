<?php

namespace Tests\Feature;

use App\Enums\RateType;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Organization;
use App\Services\CurrencyConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyConverterTest extends TestCase
{
    use RefreshDatabase;

    private CurrencyConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new CurrencyConverter();
    }

    public function test_locale_maps_to_the_expected_currency(): void
    {
        $this->assertSame('AMD', $this->converter->preferredCurrencyForLocale('hy'));
        $this->assertSame('USD', $this->converter->preferredCurrencyForLocale('en'));
        $this->assertSame('RUR', $this->converter->preferredCurrencyForLocale('ru'));
        $this->assertSame('AMD', $this->converter->preferredCurrencyForLocale('fr')); // unknown locale falls back
    }

    public function test_same_currency_returns_the_amount_unchanged(): void
    {
        $this->assertSame(1000.0, $this->converter->convert(1000, 'AMD', 'AMD'));
    }

    public function test_converts_using_the_average_bank_rate(): void
    {
        $usd = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1]);
        $this->rateFor($usd, 390, 400);
        $this->rateFor($usd, 392, 398);

        // Average non-cash rate: ((390+400)/2 + (392+398)/2) / 2 = (395 + 395) / 2 = 395
        $result = $this->converter->convert(100, 'USD', 'AMD');

        $this->assertEquals(39500.0, $result);
    }

    public function test_converts_from_amd_back_to_a_foreign_currency(): void
    {
        $usd = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1]);
        $this->rateFor($usd, 390, 400);

        $result = $this->converter->convert(39500, 'AMD', 'USD');

        $this->assertEqualsWithDelta(100.0, $result, 0.01);
    }

    public function test_returns_null_when_no_rate_data_exists(): void
    {
        Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1]);

        $this->assertNull($this->converter->convert(100, 'USD', 'AMD'));
    }

    public function test_returns_null_for_an_unknown_currency_code(): void
    {
        $this->assertNull($this->converter->convert(100, 'ZZZ', 'AMD'));
    }

    public function test_falls_back_to_cash_rate_when_no_non_cash_rate_exists(): void
    {
        $usd = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1]);
        $this->rateFor($usd, 390, 400, RateType::CASH);

        $result = $this->converter->convert(100, 'USD', 'AMD');

        $this->assertEquals(39500.0, $result);
    }

    private function rateFor(Currency $currency, float $buy, float $sell, RateType $type = RateType::NON_CASH): CurrencyRate
    {
        static $orgCount = 0;
        $orgCount++;

        $organization = Organization::create([
            'name' => 'Rate Bank ' . $orgCount, 'slug' => 'rate-bank-' . $orgCount, 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => true,
        ]);

        return CurrencyRate::create([
            'organization_id' => $organization->id,
            'currency_id' => $currency->id,
            'rate_type' => $type,
            'buy_rate' => $buy,
            'sell_rate' => $sell,
            'scraped_at' => now(),
        ]);
    }
}
