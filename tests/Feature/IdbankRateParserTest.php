<?php

namespace Tests\Feature;

use App\Parsers\IdbankRateParser;
use Tests\TestCase;

class IdbankRateParserTest extends TestCase
{
    private function fixture(): string
    {
        // Trimmed real structure: a header row, two data rows (one with a
        // leading trend-arrow SVG on its cells, one without), then the
        // "table-actions" boundary that must stop the row scan before a
        // banner further down the page that could otherwise look similar.
        return <<<HTML
        <div class="m-exchange">
            <div class="m-exchange__table">
                <div class="m-exchange__table-row m-exchange__table-row--header">
                    <div class="m-exchange__table-cell">Currency</div>
                    <div class="m-exchange__table-cell">Buy</div>
                    <div class="m-exchange__table-cell">Sell</div>
                </div>
                <div class="m-exchange__table-row">
                    <div class="m-exchange__table-cell"><svg></svg>1 USD</div>
                    <div class="m-exchange__table-cell">365</div>
                    <div class="m-exchange__table-cell">370</div>
                </div>
                <div class="m-exchange__table-row">
                    <div class="m-exchange__table-cell"><svg></svg>1 EUR</div>
                    <div class="m-exchange__table-cell"><svg></svg>413</div>
                    <div class="m-exchange__table-cell"><svg></svg>426</div>
                </div>
                <div class="m-exchange__table-actions">
                    <a href="/en/rates/">All</a>
                </div>
            </div>
        </div>
        HTML;
    }

    public function test_parses_the_cash_rate_table(): void
    {
        $rates = (new IdbankRateParser())->parse($this->fixture());

        $this->assertCount(2, $rates);
        $this->assertSame('USD', $rates[0]['code']);
        $this->assertSame('cash', $rates[0]['rate_type']);
        $this->assertSame(365.0, $rates[0]['buy']);
        $this->assertSame(370.0, $rates[0]['sell']);
        $this->assertSame(413.0, $rates[1]['buy']);
    }

    public function test_returns_empty_when_the_table_header_is_missing(): void
    {
        $rates = (new IdbankRateParser())->parse('<div>no rates here</div>');

        $this->assertSame([], $rates);
    }
}
