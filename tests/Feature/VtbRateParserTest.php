<?php

namespace Tests\Feature;

use App\Parsers\VtbRateParser;
use Tests\TestCase;

class VtbRateParserTest extends TestCase
{
    private function currencyItem(string $code, string $buy, string $sell): string
    {
        return <<<HTML
        <div class="wrapper-item">
            <div class="wrapper-currency"><strong><img src="flag.png">1 {$code}</strong></div>
            <div class="wrapper-purchase"><strong><svg></svg>{$buy}</strong></div>
            <div class="wrapper-sale"><strong><svg></svg><svg></svg>{$sell}</strong></div>
        </div>
        HTML;
    }

    private function fixture(): string
    {
        // Trimmed real structure: two Bootstrap tab panes (#home = cash,
        // #options = non-cash), each with its own wrapper-currency-data
        // block, plus a decoy converter widget reusing the same markup
        // after each block that must not be swept in.
        $cashItem = $this->currencyItem('USD', '364.5', '369');
        $nonCashItem = $this->currencyItem('USD', '364.5', '370');
        $decoy = $this->currencyItem('EUR', '999', '999');

        return <<<HTML
        <div class="tab-pane fade show active" id="home">
            <div class="wrapper-currency-data">
                {$cashItem}
            </div>
            <div class="wrapper-currency-data">
                {$decoy}
            </div>
        </div>
        <div class="tab-pane fade" id="options">
            <div class="wrapper-currency-data">
                {$nonCashItem}
            </div>
        </div>
        HTML;
    }

    public function test_parses_cash_and_non_cash_tabs_separately(): void
    {
        $rates = (new VtbRateParser())->parse($this->fixture());

        $byKey = [];
        foreach ($rates as $rate) {
            $byKey["{$rate['code']}:{$rate['rate_type']}"] = $rate;
        }

        $this->assertSame(369.0, $byKey['USD:cash']['sell']);
        $this->assertSame(370.0, $byKey['USD:non_cash']['sell']);
    }

    public function test_ignores_the_decoy_converter_widget_after_the_real_table(): void
    {
        $rates = (new VtbRateParser())->parse($this->fixture());

        $codes = array_unique(array_column($rates, 'code'));

        $this->assertSame(['USD'], $codes);
    }
}
