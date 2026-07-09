<?php

namespace Tests\Feature;

use App\Parsers\ArmeconombankRateParser;
use Tests\TestCase;

class ArmeconombankRateParserTest extends TestCase
{
    private function fixture(): string
    {
        // Real structure: HTML-entity-escaped JSON objects embedded in a
        // script block, one per currency. GBP has no card ("Arca") rate,
        // matching how AEB reports that as "0" rather than omitting it.
        $usd = '{&quot;currency&quot;:&quot;USD&quot;,&quot;buy&quot;:&quot;365&quot;,&quot;sell&quot;:&quot;370&quot;,'
            . '&quot;cbRate&quot;:&quot;367.29&quot;,&quot;buyNC&quot;:&quot;365&quot;,&quot;sellNC&quot;:&quot;370&quot;,'
            . '&quot;sellArca&quot;:&quot;373&quot;,&quot;buyArca&quot;:&quot;363&quot;,&quot;lastUpdated&quot;:&quot;2026-07-08T11:44:00&quot;}';

        $gbp = '{&quot;currency&quot;:&quot;GBP&quot;,&quot;buy&quot;:&quot;478&quot;,&quot;sell&quot;:&quot;501&quot;,'
            . '&quot;cbRate&quot;:&quot;490.11&quot;,&quot;buyNC&quot;:&quot;481&quot;,&quot;sellNC&quot;:&quot;501&quot;,'
            . '&quot;sellArca&quot;:&quot;0&quot;,&quot;buyArca&quot;:&quot;0&quot;,&quot;lastUpdated&quot;:&quot;2026-07-08T11:44:00&quot;}';

        return "<script>var rates = [{$usd},{$gbp}];</script>";
    }

    public function test_parses_cash_non_cash_card_and_central_bank_rates(): void
    {
        $rates = (new ArmeconombankRateParser())->parse($this->fixture());

        $byKey = [];
        foreach ($rates as $rate) {
            $byKey["{$rate['code']}:{$rate['rate_type']}"] = $rate;
        }

        $this->assertSame(365.0, $byKey['USD:cash']['buy']);
        $this->assertSame(370.0, $byKey['USD:cash']['sell']);
        $this->assertSame(365.0, $byKey['USD:non_cash']['buy']);
        $this->assertSame(363.0, $byKey['USD:card']['buy']);
        $this->assertSame(367.29, $byKey['USD:central_bank']['buy']);
    }

    public function test_a_zero_rate_field_does_not_produce_a_row(): void
    {
        $rates = (new ArmeconombankRateParser())->parse($this->fixture());

        $keys = array_map(fn ($r) => "{$r['code']}:{$r['rate_type']}", $rates);

        $this->assertNotContains('GBP:card', $keys);
    }
}
