<?php

namespace Tests\Feature;

use App\Parsers\FastBankRateParser;
use Tests\TestCase;

class FastBankRateParserTest extends TestCase
{
    /**
     * Trimmed from /api/exchange-rates?kind=rates&payType=, keeping the real
     * shape: all three pay types in one response, identified by a numeric
     * PayType, and a per-currency Unit.
     *
     * The USD figures are the genuine ones, and they are what pins the
     * PayType mapping down: 363/366 is the spread the page shows under
     * "Non-cash", 362.5/365.5 under "Cash".
     */
    private function fixture(): string
    {
        return <<<'JSON'
        {"Rates":[
            {"Id":"USD","Buy":363,"Sale":366,"Unit":1,"PayType":0,"PrevBuy":362,"PrevSale":366},
            {"Id":"USD","Buy":362.5,"Sale":365.5,"Unit":1,"PayType":1,"PrevBuy":362,"PrevSale":365},
            {"Id":"USD","Buy":362.5,"Sale":367.5,"Unit":1,"PayType":5,"PrevBuy":362,"PrevSale":367},
            {"Id":"KZT","Buy":74,"Sale":86,"Unit":100,"PayType":0,"PrevBuy":74,"PrevSale":86},
            {"Id":"CHF","Buy":441,"Sale":466,"Unit":1,"PayType":9,"PrevBuy":441,"PrevSale":466},
            {"Id":"JPY","Buy":0,"Sale":0,"Unit":1,"PayType":1,"PrevBuy":0,"PrevSale":0}
        ],"ResultCode":0,"ResultMessage":"OK"}
        JSON;
    }

    /** @return array<int, array{code: string, rate_type: string, buy: float, sell: float}> */
    private function parse(?string $json = null): array
    {
        return (new FastBankRateParser)->parse($json ?? $this->fixture());
    }

    /**
     * The numbering is not the one you would guess: 0 is the non-cash rate
     * and 1 is cash. Getting them the wrong way round swaps two spreads that
     * both look perfectly plausible.
     */
    public function test_it_maps_each_pay_type_code_to_the_right_rate_type(): void
    {
        $rates = $this->parse();

        $this->assertContains(['code' => 'USD', 'rate_type' => 'non_cash', 'buy' => 363.0, 'sell' => 366.0], $rates);
        $this->assertContains(['code' => 'USD', 'rate_type' => 'cash', 'buy' => 362.5, 'sell' => 365.5], $rates);
        $this->assertContains(['code' => 'USD', 'rate_type' => 'card', 'buy' => 362.5, 'sell' => 367.5], $rates);
    }

    /**
     * Rates are quoted per Unit. Ignoring it publishes a currency quoted per
     * 100 at a hundred times its real value - a number nobody would read as
     * an error, just as a spectacular rate.
     */
    public function test_it_divides_a_rate_quoted_per_hundred_units(): void
    {
        $this->assertContains(
            ['code' => 'KZT', 'rate_type' => 'non_cash', 'buy' => 0.74, 'sell' => 0.86],
            $this->parse(),
        );
    }

    public function test_it_leaves_out_a_pay_type_it_cannot_name(): void
    {
        foreach ($this->parse() as $rate) {
            $this->assertNotSame('CHF', $rate['code']);
        }
    }

    public function test_it_drops_a_currency_the_bank_is_not_trading(): void
    {
        foreach ($this->parse() as $rate) {
            $this->assertNotSame('JPY', $rate['code']);
            $this->assertGreaterThan(0.0, $rate['buy']);
        }
    }

    public function test_it_returns_nothing_when_the_endpoint_answers_with_something_other_than_json(): void
    {
        $this->assertSame([], $this->parse('<html>403</html>'));
        $this->assertSame([], $this->parse('{"ResultCode":1,"Rates":null}'));
    }
}
