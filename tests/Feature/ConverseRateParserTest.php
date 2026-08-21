<?php

namespace Tests\Feature;

use App\Enums\RateType;
use App\Parsers\ConverseRateParser;
use Tests\TestCase;

class ConverseRateParserTest extends TestCase
{
    /**
     * Trimmed from the live response of Converse's own API
     * (sapi.conversebank.am/api/v2/currencyrates), with the real field names
     * and the real oddities kept:
     *
     *  - the "Card" group mixes AMD-quoted rates in with cross rates that
     *    are quoted in RUB and EUR, told apart only by `iso2`
     *  - one row per cash group carries "currency": null
     *  - a "Metal" group sits alongside the currency groups
     *
     * Figures are the genuine ones from 2026-08-20, so the cross-rate trap
     * is arithmetically real: EUR is 421 against the dram and 92.7 against
     * the ruble, and both rows look identical apart from `iso2`.
     */
    private function fixture(): string
    {
        return <<<'JSON'
        {
            "Card": [
                {"buy":92.7,"sell":101.41,"type":"Card","iso2":"RUB","currency":{"id":5,"iso":"EUR"}},
                {"buy":79.65,"sell":86.18,"type":"Card","iso2":"RUB","currency":{"id":2,"iso":"USD"}},
                {"buy":0.11,"sell":0.14,"type":"Card","iso2":"EUR","currency":{"id":9,"iso":"CNY"}},
                {"buy":4.27,"sell":4.52,"type":"Card","iso2":"AMD","currency":{"id":3,"iso":"RUB"}}
            ],
            "Non Cash": [
                {"buy":421,"sell":431,"type":"Non Cash","iso2":"AMD","currency":{"id":5,"iso":"EUR"}},
                {"buy":362,"sell":366,"type":"Non Cash","iso2":"AMD","currency":{"id":2,"iso":"USD"}},
                {"buy":4.13,"sell":4.33,"type":"Non Cash","iso2":"AMD","currency":null}
            ],
            "Metal": [
                {"buy":51746,"sell":53375,"type":"Metal","iso2":"AMD","currency":{"id":11,"iso":"XAU"}}
            ],
            "Cash": [
                {"buy":361,"sell":366,"type":"Cash","iso2":"AMD","currency":{"id":2,"iso":"USD"}},
                {"buy":127,"sell":149,"type":"Cash","iso2":"AMD","currency":{"id":8,"iso":"GEL"}},
                {"buy":0,"sell":0,"type":"Cash","iso2":"AMD","currency":{"id":7,"iso":"JPY"}}
            ]
        }
        JSON;
    }

    /** @return array<int, array{code: string, rate_type: string, buy: float, sell: float}> */
    private function parse(?string $json = null): array
    {
        return (new ConverseRateParser)->parse($json ?? $this->fixture());
    }

    private function find(array $rates, string $code, RateType $type): ?array
    {
        foreach ($rates as $rate) {
            if ($rate['code'] === $code && $rate['rate_type'] === $type->value) {
                return $rate;
            }
        }

        return null;
    }

    public function test_it_reads_each_rate_group_into_its_matching_rate_type(): void
    {
        $rates = $this->parse();

        $this->assertSame(
            ['code' => 'USD', 'rate_type' => 'cash', 'buy' => 361.0, 'sell' => 366.0],
            $this->find($rates, 'USD', RateType::CASH),
        );
        $this->assertSame(
            ['code' => 'EUR', 'rate_type' => 'non_cash', 'buy' => 421.0, 'sell' => 431.0],
            $this->find($rates, 'EUR', RateType::NON_CASH),
        );
        $this->assertSame(
            ['code' => 'RUB', 'rate_type' => 'card', 'buy' => 4.27, 'sell' => 4.52],
            $this->find($rates, 'RUB', RateType::CARD),
        );
    }

    /**
     * The one that matters. A row quoted in RUB carries the same shape as an
     * AMD-quoted one, and this app has nowhere to record a quote currency -
     * so taking it would publish EUR at 92.7 dram instead of 421, as a real
     * quote on the comparison page rather than an obvious gap.
     */
    public function test_it_ignores_rates_quoted_in_something_other_than_the_dram(): void
    {
        $rates = $this->parse();

        $this->assertNull(
            $this->find($rates, 'EUR', RateType::CARD),
            'The EUR/RUB cross rate was taken as though it were quoted in dram.',
        );
        $this->assertNull($this->find($rates, 'USD', RateType::CARD));
        $this->assertNull($this->find($rates, 'CNY', RateType::CARD));

        foreach ($rates as $rate) {
            $this->assertGreaterThan(
                1.0,
                $rate['buy'],
                "{$rate['code']} came through at {$rate['buy']}, which is a cross rate, not a dram rate.",
            );
        }
    }

    public function test_it_skips_a_row_whose_currency_is_missing(): void
    {
        foreach ($this->parse() as $rate) {
            $this->assertNotSame('', $rate['code']);
        }

        // 2 cash (JPY is untraded) + 2 non-cash (one row has no currency)
        // + 1 card (the other three are cross rates).
        $this->assertCount(5, $this->parse());
    }

    public function test_it_leaves_precious_metals_out_of_the_currency_table(): void
    {
        foreach ($this->parse() as $rate) {
            $this->assertNotSame('XAU', $rate['code']);
        }
    }

    public function test_it_drops_a_currency_the_bank_is_not_trading_today(): void
    {
        $this->assertNull($this->find($this->parse(), 'JPY', RateType::CASH));
    }

    public function test_it_returns_nothing_when_the_api_answers_with_something_other_than_json(): void
    {
        $this->assertSame([], $this->parse('<html><body>502 Bad Gateway</body></html>'));
        $this->assertSame([], $this->parse(''));
        $this->assertSame([], $this->parse('null'));
    }
}
