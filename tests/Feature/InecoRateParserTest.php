<?php

namespace Tests\Feature;

use App\Parsers\InecoRateParser;
use Tests\TestCase;

class InecoRateParserTest extends TestCase
{
    /**
     * Trimmed from https://www.inecobank.am/api/rates, keeping the real
     * shape: every category present on every currency, with nulls where the
     * bank does not trade that way (GBP has no cash rate, gold has nothing).
     */
    private function fixture(): string
    {
        return <<<'JSON'
        {"success":true,"items":[
            {"code":"USD",
             "cash":{"buy":362,"sell":367},
             "cashless":{"buy":362.5,"sell":367},
             "online":{"buy":362.73,"sell":367},
             "cb":{"buy":365.26,"sell":365.26},
             "card":{"buy":362.5,"sell":368}},
            {"code":"GBP",
             "cash":{"buy":null,"sell":null},
             "cashless":{"buy":489,"sell":504},
             "online":{"buy":489,"sell":504},
             "cb":{"buy":498.32,"sell":498.32},
             "card":{"buy":null,"sell":null}},
            {"code":"XAU",
             "cash":{"buy":null,"sell":null},
             "cashless":{"buy":null,"sell":null},
             "online":{"buy":null,"sell":null},
             "cb":{"buy":null,"sell":null},
             "card":{"buy":null,"sell":null}}
        ]}
        JSON;
    }

    /** @return array<int, array{code: string, rate_type: string, buy: float, sell: float}> */
    private function parse(?string $json = null): array
    {
        return (new InecoRateParser)->parse($json ?? $this->fixture());
    }

    public function test_it_reads_every_category_the_bank_quotes(): void
    {
        $rates = $this->parse();

        $this->assertContains(['code' => 'USD', 'rate_type' => 'cash', 'buy' => 362.0, 'sell' => 367.0], $rates);
        $this->assertContains(['code' => 'USD', 'rate_type' => 'non_cash', 'buy' => 362.5, 'sell' => 367.0], $rates);
        $this->assertContains(['code' => 'USD', 'rate_type' => 'card', 'buy' => 362.5, 'sell' => 368.0], $rates);
        $this->assertContains(['code' => 'USD', 'rate_type' => 'central_bank', 'buy' => 365.26, 'sell' => 365.26], $rates);
    }

    /**
     * The categories are always present, so absence is expressed as null
     * rather than by leaving the key out. Casting those would publish a 0.
     */
    public function test_it_skips_a_category_the_bank_does_not_trade(): void
    {
        $gbp = array_filter($this->parse(), fn ($r) => $r['code'] === 'GBP');

        $this->assertEqualsCanonicalizing(
            ['non_cash', 'central_bank'],
            array_column($gbp, 'rate_type'),
        );

        foreach ($this->parse() as $rate) {
            $this->assertGreaterThan(0.0, $rate['buy']);
            $this->assertGreaterThan(0.0, $rate['sell']);
            $this->assertNotSame('XAU', $rate['code']);
        }
    }

    /**
     * The bank's in-app "online" rate has no matching RateType, and the
     * nearest cases mean something else. Publishing it under one of those
     * would misdescribe it, so it is left out until the enum has a home.
     */
    public function test_it_leaves_out_the_online_rate_it_cannot_name(): void
    {
        foreach ($this->parse() as $rate) {
            $this->assertNotSame(362.73, $rate['buy'], 'The online rate was published under another type.');
        }
    }

    public function test_it_returns_nothing_when_the_endpoint_is_challenged_rather_than_answering(): void
    {
        $this->assertSame([], $this->parse('<html><title>Just a moment...</title></html>'));
        $this->assertSame([], $this->parse('{"success":false}'));
    }
}
