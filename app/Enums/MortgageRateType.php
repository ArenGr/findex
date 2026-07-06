<?php

namespace App\Enums;

enum MortgageRateType: string
{
    case FIXED = 'fixed';
    case FLOATING_1Y = 'floating_1y';
    case FLOATING_3Y = 'floating_3y';
    case FLOATING_5Y = 'floating_5y';
    case FLOATING_7Y = 'floating_7y';
    case FLOATING_10Y = 'floating_10y';
}
