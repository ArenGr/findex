<?php

namespace Tests\Feature;

use App\Enums\RateType;
use App\Parsers\ArdshinbankRateParser;
use Tests\TestCase;

class ArdshinbankRateParserTest extends TestCase
{
    /**
     * Trimmed from https://ardshinbank.am/api/currency, keeping the real
     * shape: figures as strings, a small one written without its leading
     * zero (KZT), the same `cb` repeated on the cash and non-cash rows, and
     * the separate gold branch.
     */
    private function fixture(): string
    {
        return <<<'JSON'
        {"data":{
            "currencies":{
                "cash":[
                    {"type":"USD","buy":"362","sell":"367","cb":"365.26"},
                    {"type":"RUR","buy":"4.13","sell":"4.55","cb":"4.3489"}
                ],
                "no_cash":[
                    {"type":"USD","buy":"362.5","sell":"367.5","cb":"365.26"},
                    {"type":"RUR","buy":"4.21","sell":"4.55","cb":"4.3489"},
                    {"type":"KZT","buy":".77","sell":".84","cb":".8018"},
                    {"type":"SEK","buy":"0","sell":"0","cb":"0"}
                ]
            },
            "gold":{
                "cash":[{"quantity":"1 gram gold","rate":"70000"}],
                "no_cash":[{"type":"XAU","buy":"47094.81","sell":"58365.77","cb":"52384"}]
            }
        }}
        JSON;
    }

    /** @return array<int, array{code: string, rate_type: string, buy: float, sell: float}> */
    private function parse(?string $json = null): array
    {
        return (new ArdshinbankRateParser)->parse($json ?? $this->fixture());
    }

    private function ofType(array $rates, RateType $type): array
    {
        return array_values(array_filter($rates, fn ($r) => $r['rate_type'] === $type->value));
    }

    public function test_it_reads_the_cash_and_non_cash_spreads(): void
    {
        $rates = $this->parse();

        $this->assertContains(
            ['code' => 'USD', 'rate_type' => 'cash', 'buy' => 362.0, 'sell' => 367.0],
            $rates,
        );
        $this->assertContains(
            ['code' => 'USD', 'rate_type' => 'non_cash', 'buy' => 362.5, 'sell' => 367.5],
            $rates,
        );
    }

    public function test_it_reads_a_figure_written_without_its_leading_zero(): void
    {
        $this->assertContains(
            ['code' => 'KZT', 'rate_type' => 'non_cash', 'buy' => 0.77, 'sell' => 0.84],
            $this->parse(),
        );
    }

    /**
     * The central bank's reference rate belongs to the currency, not to how
     * it is traded, so the API repeats it on both the cash and non-cash
     * rows. Publishing it twice would put two identical rows in the table.
     */
    public function test_it_publishes_one_central_bank_rate_per_currency(): void
    {
        $central = $this->ofType($this->parse(), RateType::CENTRAL_BANK);
        $codes = array_column($central, 'code');

        $this->assertSame(array_unique($codes), $codes, 'A central bank rate was published twice.');
        $this->assertEqualsCanonicalizing(['USD', 'RUR', 'KZT'], $codes);

        $usd = $central[array_search('USD', $codes, true)];
        $this->assertSame(365.26, $usd['buy']);
        $this->assertSame(365.26, $usd['sell'], 'A reference rate has no spread - both sides carry it.');
    }

    public function test_it_leaves_gold_out_of_the_currency_table(): void
    {
        foreach ($this->parse() as $rate) {
            $this->assertNotSame('XAU', $rate['code']);
            $this->assertNotSame(70000.0, $rate['buy']);
        }
    }

    public function test_it_drops_a_currency_the_bank_is_not_trading(): void
    {
        foreach ($this->parse() as $rate) {
            $this->assertNotSame('SEK', $rate['code']);
        }
    }

    public function test_it_returns_nothing_when_the_api_answers_with_something_other_than_json(): void
    {
        $this->assertSame([], $this->parse('<html>503</html>'));
        $this->assertSame([], $this->parse(''));
    }
}
