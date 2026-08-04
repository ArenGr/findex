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
        // Everything below is roughly scaled to "worth about as much as
        // 1000 USD", same logic as the three above - not live-converted,
        // just sensible round numbers so the bar for "worth renegotiating
        // a rate over" stays consistent across currencies of very
        // different unit value.
        'GBP' => 800,
        'CHF' => 1000,
        'GEL' => 3000,
        'AED' => 3500,
        'CNY' => 7000,
        'KZT' => 500000,
        'CAD' => 1400,
        'AUD' => 1500,
    ],
];
