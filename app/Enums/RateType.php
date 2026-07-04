<?php

namespace App\Enums;

enum RateType: string
{
    case CASH = 'cash';
    case NON_CASH = 'non_cash';
    case CARD = 'card';
    case TRANSFER = 'transfer';
    case CROSS = 'cross';
    case CENTRAL_BANK = 'central_bank';
}
