<?php

namespace Tests\Feature;

use App\Parsers\MellatRateParser;
use Tests\TestCase;

class MellatRateParserTest extends TestCase
{
    /**
     * Trimmed from https://api.mellatbank.am/api/v1/rate/list, keeping the
     * dram row the API includes - quoted against itself at 1/1.
     */
    private function fixture(): string
    {
        return <<<'JSON'
        {"status":200,"message":[],"result":{"data":[
            {"_id":"a1","currency":"USD","buy":362,"sell":367,"buyCash":361,"sellCash":368},
            {"_id":"a2","currency":"RUB","buy":4,"sell":4.5,"buyCash":4,"sellCash":4.5},
            {"_id":"a3","currency":"AMD","buy":1,"sell":1,"buyCash":1,"sellCash":1}
        ]}}
        JSON;
    }

    /** @return array<int, array{code: string, rate_type: string, buy: float, sell: float}> */
    private function parse(?string $json = null): array
    {
        return (new MellatRateParser)->parse($json ?? $this->fixture());
    }

    public function test_it_reads_the_cash_and_non_cash_spreads(): void
    {
        $rates = $this->parse();

        $this->assertContains(
            ['code' => 'USD', 'rate_type' => 'non_cash', 'buy' => 362.0, 'sell' => 367.0],
            $rates,
        );
        $this->assertContains(
            ['code' => 'USD', 'rate_type' => 'cash', 'buy' => 361.0, 'sell' => 368.0],
            $rates,
        );
    }

    /**
     * The dram is what every other row is priced in, not something the bank
     * trades. Left in, it would show as "AMD 1.00 / 1.00" on the comparison
     * page.
     */
    public function test_it_does_not_publish_the_dram_against_itself(): void
    {
        foreach ($this->parse() as $rate) {
            $this->assertNotSame('AMD', $rate['code']);
        }

        $this->assertCount(4, $this->parse());
    }

    public function test_it_returns_nothing_when_the_api_answers_with_something_other_than_json(): void
    {
        $this->assertSame([], $this->parse('<html>502</html>'));
        $this->assertSame([], $this->parse('{"status":500,"result":null}'));
    }
}
