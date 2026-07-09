<?php

namespace App\Parsers;

use App\Enums\RateType;

class IdbankRateParser implements RateParser
{
    /**
     * IDBank's /rates/ page (and homepage widget) renders a plain
     * server-side div-based table - only the cash rate is shown here (a
     * dropdown for "Mobile"/"Gold" rate types exists but only swaps via a
     * client-side request, so those aren't available from a single GET):
     *
     *   <div class="m-exchange__table-row">
     *     <div class="m-exchange__table-cell"><svg>...</svg>1 USD</div>
     *     <div class="m-exchange__table-cell">365</div>
     *     <div class="m-exchange__table-cell">370</div>
     *   </div>
     *
     * Buy/sell cells occasionally carry a leading trend-arrow SVG before the
     * number.
     */
    private const ROW_PATTERN = '/<div class="m-exchange__table-row">\s*<div class="m-exchange__table-cell">.*?(?:\d+)\s+([A-Z]{3,4}).*?<\/div>\s*'
        . '<div class="m-exchange__table-cell">\s*(?:<svg.*?<\/svg>)?\s*([\d.]+)\s*<\/div>\s*'
        . '<div class="m-exchange__table-cell">\s*(?:<svg.*?<\/svg>)?\s*([\d.]+)\s*<\/div>/s';

    public function parse(string $html): array
    {
        if (!preg_match(
            '/<div class="m-exchange__table-row m-exchange__table-row--header">.*?<\/div>\s*<\/div>(.*?)<div class="m-exchange__table-actions">/s',
            $html,
            $tableMatch
        )) {
            return [];
        }

        preg_match_all(self::ROW_PATTERN, $tableMatch[1], $matches, PREG_SET_ORDER);

        return array_map(fn ($m) => [
            'code' => $m[1],
            'rate_type' => RateType::CASH->value,
            'buy' => (float) $m[2],
            'sell' => (float) $m[3],
        ], $matches);
    }
}
