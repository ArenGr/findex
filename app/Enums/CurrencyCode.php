<?php

namespace App\Enums;

/**
 * The set of currencies we track across every organization. A bank may
 * publish more currencies than this - anything outside this list is
 * discarded during parsing/saving.
 */
enum CurrencyCode: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case CHF = 'CHF';
    case RUB = 'RUB';
    case GEL = 'GEL';

    /**
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return array_column(self::cases(), 'value');
    }
}
