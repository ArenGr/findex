<?php

namespace Tests\Feature;

use App\Parsers\AraratbankRateParser;
use Tests\TestCase;

class AraratbankRateParserTest extends TestCase
{
    private function fixture(): string
    {
        // Trimmed real structure: a "cash" wrapper table, a decoy gold-price
        // table (reusing the same cell markup) that must NOT be swept in,
        // then the "not-cash" wrapper table.
        return <<<HTML
        <div class="exchange__wrapper cash">
            <table class="exchange__table"><tbody>
                <tr>
                    <td class="exchange__table-cell fb fs20">USD</td>
                    <td class="exchange__table-cell fs20">364.5</td>
                    <td class="exchange__table-cell fs20">369.5</td>
                    <td class="exchange__table-cell fs20">367.29</td>
                </tr>
                <tr>
                    <td class="exchange__table-cell fb fs20">EUR</td>
                    <td class="exchange__table-cell fs20">413</td>
                    <td class="exchange__table-cell fs20">425</td>
                    <td class="exchange__table-cell fs20">419</td>
                </tr>
            </tbody></table>
            <div class="exchange__additional-box"><button>See more</button></div>
        </div>
        <table class="exchange__table"><tbody>
            <tr><td class="exchange__table-cell fb fs20">USD/1g</td><td class="exchange__table-cell fs20">100</td></tr>
        </tbody></table>
        <div class="exchange__wrapper not-cash dn">
            <table class="exchange__table"><tbody>
                <tr>
                    <td class="exchange__table-cell fb fs20">USD</td>
                    <td class="exchange__table-cell fs20">364.5</td>
                    <td class="exchange__table-cell fs20">369.5</td>
                    <td class="exchange__table-cell fs20">367.29</td>
                </tr>
            </tbody></table>
            <div class="exchange__additional-box"><button>See more</button></div>
        </div>
        HTML;
    }

    public function test_parses_cash_and_non_cash_rows_with_a_central_bank_rate(): void
    {
        $rates = (new AraratbankRateParser())->parse($this->fixture());

        $byKey = [];
        foreach ($rates as $rate) {
            $byKey["{$rate['code']}:{$rate['rate_type']}"] = $rate;
        }

        $this->assertSame(364.5, $byKey['USD:cash']['buy']);
        $this->assertSame(369.5, $byKey['USD:cash']['sell']);
        $this->assertSame(364.5, $byKey['USD:non_cash']['buy']);
        $this->assertSame(367.29, $byKey['USD:central_bank']['buy']);
        $this->assertSame(413.0, $byKey['EUR:cash']['buy']);
    }

    public function test_ignores_the_gold_table_between_the_two_wrappers(): void
    {
        $rates = (new AraratbankRateParser())->parse($this->fixture());

        $codes = array_unique(array_column($rates, 'code'));

        $this->assertNotContains('USD/1g', $codes);
    }

    public function test_returns_empty_when_the_not_cash_wrapper_is_missing(): void
    {
        $rates = (new AraratbankRateParser())->parse('<html><body>no rates here</body></html>');

        $this->assertSame([], $rates);
    }
}
