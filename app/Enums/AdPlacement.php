<?php

namespace App\Enums;

enum AdPlacement: string
{
    case ORGANIZATIONS_INDEX = 'organizations_index';
    case HOME_RATES = 'home_rates';
    case HOME_HERO = 'home_hero';
}
