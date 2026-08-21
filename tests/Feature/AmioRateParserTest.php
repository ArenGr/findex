<?php

namespace Tests\Feature;

use App\Parsers\AmioRateParser;
use Tests\TestCase;

class AmioRateParserTest extends TestCase
{
    /**
     * Trimmed from the real page. AMIO is a Next.js app, so the rates never
     * appear as an HTML table - they are hydration props in a
     * <script id="__NEXT_DATA__"> block, which is what the parser reads.
     *
     * The surrounding <svg> is deliberate: the logo's path coordinates look
     * exactly like exchange rates ("395.1", "376.3") and a naive number
     * scrape picks them up instead of the real figures.
     */
    private function fixture(?string $ratesJson = null): string
    {
        $ratesJson ??= <<<'JSON'
        {
            "cash": [
                {"currency":"USD","buyValue":"362.50","sellValue":"367.50","isCash":true,"branchIndex":"00"},
                {"currency":"RUB","buyValue":"4.12","sellValue":"4.38","isCash":true,"branchIndex":"00"}
            ],
            "card": [
                {"currency":"USD","buyValue":"362.50","sellValue":"368.90","isCash":false,"branchIndex":"00"},
                {"currency":"GEL","buyValue":"133.00","sellValue":"148.00","isCash":false,"branchIndex":"00"}
            ]
        }
        JSON;

        return <<<HTML
        <div id="__next">
            <svg viewBox="0 0 625.4 245.3">
                <rect x="395.1" y="58.4" width="38.7" height="128.8"></rect>
                <polygon points="178.6 58.4 376.3 187.2 337.6 94.4"></polygon>
            </svg>
            <div id="rates-table"></div>
        </div>
        <script id="__NEXT_DATA__" type="application/json">
        {"props":{"pageProps":{"data":{"rates":{$ratesJson}}}}}
        </script>
        HTML;
    }

    private function keyed(array $rates): array
    {
        $byKey = [];

        foreach ($rates as $rate) {
            $byKey["{$rate['code']}:{$rate['rate_type']}"] = $rate;
        }

        return $byKey;
    }

    public function test_parses_cash_and_card_rates_from_the_hydration_payload(): void
    {
        $rates = $this->keyed((new AmioRateParser)->parse($this->fixture()));

        $this->assertCount(4, $rates);

        $this->assertSame(362.5, $rates['USD:cash']['buy']);
        $this->assertSame(367.5, $rates['USD:cash']['sell']);
        $this->assertSame(4.12, $rates['RUB:cash']['buy']);

        // The bank calls its non-cash rate "card"; every other parser here
        // reports that distinction as non_cash.
        $this->assertSame(368.9, $rates['USD:non_cash']['sell']);
        $this->assertSame(133.0, $rates['GEL:non_cash']['buy']);
    }

    /**
     * The logo's SVG coordinates are numerically indistinguishable from
     * exchange rates, so a parser that scraped numbers out of the markup
     * would happily report them.
     */
    public function test_does_not_mistake_svg_coordinates_for_rates(): void
    {
        $rates = (new AmioRateParser)->parse($this->fixture());

        foreach ($rates as $rate) {
            $this->assertNotContains($rate['buy'], [395.1, 376.3, 178.6, 337.6]);
            $this->assertNotContains($rate['sell'], [395.1, 376.3, 178.6, 337.6]);
        }
    }

    public function test_skips_branches_other_than_the_head_office(): void
    {
        $rates = (new AmioRateParser)->parse($this->fixture(<<<'JSON'
        {
            "cash": [
                {"currency":"USD","buyValue":"362.50","sellValue":"367.50","branchIndex":"00"},
                {"currency":"USD","buyValue":"360.00","sellValue":"370.00","branchIndex":"07"}
            ]
        }
        JSON));

        $this->assertCount(1, $rates);
        $this->assertSame(367.5, $rates[0]['sell']);
    }

    /**
     * A zero means the bank is not quoting that currency, not that it trades
     * at nothing - storing it would show an absurd rate on the comparison.
     */
    public function test_skips_currencies_quoted_at_zero(): void
    {
        $rates = (new AmioRateParser)->parse($this->fixture(<<<'JSON'
        {
            "cash": [
                {"currency":"USD","buyValue":"362.50","sellValue":"367.50","branchIndex":"00"},
                {"currency":"CHF","buyValue":"0","sellValue":"0","branchIndex":"00"}
            ]
        }
        JSON));

        $this->assertCount(1, $rates);
        $this->assertSame('USD', $rates[0]['code']);
    }

    /**
     * A redesign that drops the payload must yield nothing, so the scraper's
     * existing zero-records alarm fires (see RateScraper) rather than the
     * parser inventing data or blowing up.
     */
    public function test_returns_nothing_when_the_payload_is_absent_or_malformed(): void
    {
        $this->assertSame([], (new AmioRateParser)->parse('<html><body>redesigned</body></html>'));
        $this->assertSame([], (new AmioRateParser)->parse('<script id="__NEXT_DATA__">not json</script>'));
        $this->assertSame([], (new AmioRateParser)->parse($this->fixture('{"cash":[{"currency":"USD"}]}')));
    }
}
