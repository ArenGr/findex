<?php

namespace Tests\Feature;

use App\Parsers\ArmswissbankRateParser;
use Tests\TestCase;

class ArmswissbankRateParserTest extends TestCase
{
    /**
     * Trimmed from https://www.armswissbank.am/include/ajax.php, keeping the
     * real shape: BID/OFFER are the non-cash pair and BID_cash/OFFER_cash
     * the cash one, "0.00" marks a currency not traded in that form, and the
     * response carries unrelated market data alongside the rates.
     */
    private function fixture(): string
    {
        return <<<'JSON'
        {"lmasbrate":[
            {"ISO":"USD","CURRENCY":"US dollar","BID":"363.00","OFFER":"367.00","hert":"1","BID_cash":"362.00","OFFER_cash":"368.00"},
            {"ISO":"RUB","CURRENCY":"Ruble","BID":"4.26","OFFER":"4.45","hert":"5","BID_cash":"4.14","OFFER_cash":"4.41"},
            {"ISO":"SEK","CURRENCY":"Krona","BID":"38.50","OFFER":"40.50","hert":"6","BID_cash":"0.00","OFFER_cash":"0.00"},
            {"ISO":"CNH","CURRENCY":"Yuan","BID":"53.81","OFFER":"54.98","hert":"7","BID_cash":"0.00","OFFER_cash":"0.00"}
        ],
        "lmgoldRate":[{"ISO":"XAU","BID":"52159.00"}],
        "imlibor":[{"rate":"4.5"}]}
        JSON;
    }

    /** @return array<int, array{code: string, rate_type: string, buy: float, sell: float}> */
    private function parse(?string $json = null): array
    {
        return (new ArmswissbankRateParser)->parse($json ?? $this->fixture());
    }

    /**
     * The two pairs sit on one row and are easy to transpose, which would
     * publish the cash spread as the non-cash one and vice versa.
     */
    public function test_it_keeps_the_cash_and_non_cash_pairs_on_their_own_sides(): void
    {
        $rates = $this->parse();

        $this->assertContains(
            ['code' => 'USD', 'rate_type' => 'non_cash', 'buy' => 363.0, 'sell' => 367.0],
            $rates,
        );
        $this->assertContains(
            ['code' => 'USD', 'rate_type' => 'cash', 'buy' => 362.0, 'sell' => 368.0],
            $rates,
        );
    }

    /**
     * SEK and CNH are quoted non-cash only; the bank writes "0.00" for the
     * cash side and its own page renders that as a dash.
     */
    public function test_it_does_not_publish_a_zero_as_though_it_were_a_rate(): void
    {
        foreach ($this->parse() as $rate) {
            $this->assertGreaterThan(0.0, $rate['buy']);
            $this->assertGreaterThan(0.0, $rate['sell']);
        }

        $cash = array_filter($this->parse(), fn ($r) => $r['rate_type'] === 'cash');
        $this->assertEqualsCanonicalizing(['USD', 'RUB'], array_column($cash, 'code'));
    }

    /**
     * Normalising codes is the scraper's job, not the parser's (see
     * RateParser) - this bank's CNH and RUB are folded to the app's CNY and
     * RUR downstream, and RateCurrencyAliasTest guards that mapping.
     */
    public function test_it_reports_the_codes_the_bank_publishes_rather_than_normalising_them(): void
    {
        $codes = array_column($this->parse(), 'code');

        $this->assertContains('CNH', $codes);
        $this->assertContains('RUB', $codes);
    }

    public function test_it_ignores_the_other_market_data_in_the_response(): void
    {
        foreach ($this->parse() as $rate) {
            $this->assertNotSame('XAU', $rate['code']);
        }
    }

    public function test_it_returns_nothing_when_the_endpoint_answers_with_something_other_than_json(): void
    {
        $this->assertSame([], $this->parse('<html>error</html>'));
        $this->assertSame([], $this->parse('{"lmasbrate":"not-a-list"}'));
    }
}
