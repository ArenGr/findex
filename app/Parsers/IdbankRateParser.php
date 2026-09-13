<?php

namespace App\Parsers;

use App\Enums\RateType;

class IdbankRateParser implements RateParser
{
    private const ROW_PATTERN = '/<div class="m-exchange__table-row">\s*<div class="m-exchange__table-cell">.*?(?:\d+)\s+([A-Z]{3,4}).*?<\/div>\s*'
        .'<div class="m-exchange__table-cell">\s*(?:<svg.*?<\/svg>)?\s*([\d.]+)\s*<\/div>\s*'
        .'<div class="m-exchange__table-cell">\s*(?:<svg.*?<\/svg>)?\s*([\d.]+)\s*<\/div>/s';

    public function parse(string $html): array
    {
        if (! preg_match(
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
