<?php

namespace Tests\Feature;

use App\Parsers\ArtsakhbankRateParser;
use Tests\TestCase;

class ArtsakhbankRateParserTest extends TestCase
{
    private function fixture(): string
    {
        // Trimmed real structure: the "cash_section"/"non_cash_section"
        // names appear twice - once as a tab button's data-tab attribute,
        // once as the actual content div's class - the parser must land on
        // the latter, not stop at the button.
        return <<<HTML
        <ul class="tab_buttons">
            <li><a data-tab="cash_section">Cash</a></li>
            <li><a data-tab="non_cash_section">Non cash</a></li>
        </ul>
        <div class="tab_block cash_section selected">
            <table>
                <tr><td>Currency</td><td>Unit</td><td>Buy</td><td>Sell</td></tr>
                <tr><td>USD</td><td>1</td><td>365.00</td><td>369.50</td></tr>
            </table>
        </div>
        <div class="tab_block non_cash_section">
            <table>
                <tr><td>Currency</td><td>Unit</td><td>Buy</td><td>Sell</td></tr>
                <tr><td>USD</td><td>1</td><td>365.00</td><td>370.00</td></tr>
            </table>
        </div>
        HTML;
    }

    public function test_parses_cash_and_non_cash_sections_separately(): void
    {
        $rates = (new ArtsakhbankRateParser())->parse($this->fixture());

        $byKey = [];
        foreach ($rates as $rate) {
            $byKey["{$rate['code']}:{$rate['rate_type']}"] = $rate;
        }

        $this->assertSame(369.5, $byKey['USD:cash']['sell']);
        $this->assertSame(370.0, $byKey['USD:non_cash']['sell']);
    }

    public function test_lands_on_the_content_div_not_the_earlier_tab_button(): void
    {
        // If the parser incorrectly anchored on the tab button's
        // data-tab="non_cash_section" (which appears before the cash
        // table), it would capture the cash table's values for non_cash too.
        $rates = (new ArtsakhbankRateParser())->parse($this->fixture());

        $byKey = [];
        foreach ($rates as $rate) {
            $byKey["{$rate['code']}:{$rate['rate_type']}"] = $rate;
        }

        $this->assertNotSame($byKey['USD:cash']['sell'], $byKey['USD:non_cash']['sell']);
    }
}
