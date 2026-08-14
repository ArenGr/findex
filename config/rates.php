<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Everyday currencies
    |--------------------------------------------------------------------------
    |
    | The four almost everyone arriving at /rates came for: the dollar and euro
    | people save in, the ruble that comes with remittances and Russian
    | arrivals, and the lari for the Georgian border. The other seven are real
    | and quoted, but showing all eleven at once turns one decision into
    | eleven and pushes the rates themselves off the screen.
    |
    | Anything not listed here still appears - one click away, behind "More
    | currencies" - and a currency chosen from there stays visible.
    |
    | Codes are matched as published in the currencies table; RUR rather than
    | RUB, which is what Armenian banks quote.
    |
    */

    'everyday' => ['USD', 'EUR', 'RUR', 'GEL'],

];
