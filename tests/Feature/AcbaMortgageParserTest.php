<?php

namespace Tests\Feature;

use App\Parsers\AcbaMortgageParser;
use Tests\TestCase;

class AcbaMortgageParserTest extends TestCase
{
    private function rateSpan(string $value): string
    {
        return '<span class="text-body-common font-semibold text-black lg:text-body-lg">'.$value.'%</span>';
    }

    private function tierHtml(string $heading, array $rates): string
    {
        return '<h4>'.$heading.'</h4>'.implode('', array_map($this->rateSpan(...), $rates));
    }

    public function test_parses_three_columns_as_amd_eur_usd_in_order(): void
    {
        $html = $this->tierHtml('Fixed annual interest rate', ['10.5', '9.5', '9.0']);

        $offers = (new AcbaMortgageParser)->parse($html);

        $this->assertSame('AMD', $offers[0]['currency']);
        $this->assertSame(10.5, $offers[0]['rate_min']);
        $this->assertSame('EUR', $offers[1]['currency']);
        $this->assertSame('USD', $offers[2]['currency']);
    }

    public function test_throws_instead_of_misattributing_when_a_column_is_missing(): void
    {
        // Only two rate spans where three are expected - e.g.
        $html = $this->tierHtml('Fixed annual interest rate', ['10.5', '9.5']);

        $this->expectException(\RuntimeException::class);

        (new AcbaMortgageParser)->parse($html);
    }

    public function test_ignores_tiers_that_are_not_present_on_the_page(): void
    {
        $html = $this->tierHtml('Fixed annual interest rate', ['10.5', '9.5', '9.0']);

        $offers = (new AcbaMortgageParser)->parse($html);

        $this->assertCount(3, $offers);
    }
}
