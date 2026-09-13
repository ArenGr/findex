<?php

namespace App\Enums;

// The set of currencies we track across every organization.
enum CurrencyCode: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case CHF = 'CHF';
    case RUB = 'RUR';
    case GEL = 'GEL';
    case AED = 'AED';
    case CNY = 'CNY';
    case KZT = 'KZT';
    case CAD = 'CAD';
    case AUD = 'AUD';

    /**
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return array_column(self::cases(), 'value');
    }
}
