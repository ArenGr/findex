<?php

namespace Tests\Feature;

use App\Parsers\ByblosRateParser;
use Tests\TestCase;

class ByblosRateParserTest extends TestCase
{
    /**
     * The shape of the real page: three tables sharing one class, where the
     * third publishes interest percentages rather than exchange rates.
     */
    private function fixture(string $extra = ''): string
    {
        return <<<HTML
        <div class="currency_part">
            <table class="currency_table fluid-x">
                <tr><th>Currency</th><th>Buy</th><th>Sell</th></tr>
                <tr><td>USD</td><td>362.00</td><td>366.50</td></tr>
                <tr><td>RUB</td><td>3.13</td><td>4.60</td></tr>
            </table>
            <table class="currency_table fluid-x">
                <tr><th>Currency</th><th>Buy</th><th>Sell</th></tr>
                <tr><td>USD</td><td>362.00</td><td>367.00</td></tr>
                <tr><td>GBP</td><td>1,486.00</td><td>1,506.00</td></tr>
            </table>
            <table class="currency_table fluid-x">
                <tr><th>Currency</th><th>Percent</th></tr>
                <tr><td>AMD</td><td>8.27%</td></tr>
                <tr><td>USD</td><td>4.36%</td></tr>
            </table>
            {$extra}
        </div>
        HTML;
    }

    /** @return array<int, array{code: string, rate_type: string, buy: float, sell: float}> */
    private function parse(?string $html = null): array
    {
        return (new ByblosRateParser)->parse($html ?? $this->fixture());
    }

    public function test_it_reads_the_first_table_as_cash_and_the_second_as_non_cash(): void
    {
        $rates = $this->parse();

        $this->assertContains(
            ['code' => 'USD', 'rate_type' => 'cash', 'buy' => 362.0, 'sell' => 366.5],
            $rates,
        );
        $this->assertContains(
            ['code' => 'USD', 'rate_type' => 'non_cash', 'buy' => 362.0, 'sell' => 367.0],
            $rates,
        );
    }

    /**
     * The one that matters. The bank's base-rate table carries the same
     * class and the same first column as the rate tables, so a positional
     * read would store USD at 4.36 dram - a plausible-looking number rather
     * than an obviously broken one.
     */
    public function test_it_ignores_the_base_rate_table_of_interest_percentages(): void
    {
        foreach ($this->parse() as $rate) {
            $this->assertNotSame(4.36, $rate['buy'], 'An interest percentage was stored as an exchange rate.');
            $this->assertNotSame(8.27, $rate['buy']);
            $this->assertNotSame('AMD', $rate['code']);
        }

        $this->assertCount(4, $this->parse());
    }

    public function test_it_reads_a_figure_printed_with_a_thousands_separator(): void
    {
        $this->assertContains(
            ['code' => 'GBP', 'rate_type' => 'non_cash', 'buy' => 1486.0, 'sell' => 1506.0],
            $this->parse(),
        );
    }

    /**
     * If the bank adds a third rate table, its type cannot be guessed from
     * position. Filing it under a wrong type is worse than leaving it out,
     * and the two known tables must keep publishing either way.
     */
    public function test_it_leaves_out_a_rate_table_it_cannot_name(): void
    {
        $extra = '<table class="currency_table fluid-x">'
            .'<tr><th>Currency</th><th>Buy</th><th>Sell</th></tr>'
            .'<tr><td>CHF</td><td>441.00</td><td>466.00</td></tr></table>';

        foreach ($this->parse($this->fixture($extra)) as $rate) {
            $this->assertNotSame('CHF', $rate['code']);
        }

        $this->assertCount(4, $this->parse($this->fixture($extra)));
    }

    public function test_it_returns_nothing_when_the_page_carries_no_rate_table(): void
    {
        $this->assertSame([], $this->parse('<html><body>Down for maintenance</body></html>'));
        $this->assertSame([], $this->parse(''));
    }
}
