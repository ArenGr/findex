<?php

return [
    /*
     * The minimum amount (in the currency's own units) a request must meet
     * to qualify - below this, the transaction isn't large enough to be
     * worth an exchange office renegotiating their posted rate for. Keyed
     * by currency code; a currency not listed here simply isn't offered on
     * the request form.
     */
    'minimum_amounts' => [
        'USD' => 1000,
        'EUR' => 1000,
        'RUR' => 100000,
    ],
];
