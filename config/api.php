<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plans
    |--------------------------------------------------------------------------
    |
    | Commercial terms, kept in configuration rather than in code. Prices and
    | limits are a business decision that will change without any application
    | change, so nothing below is referenced by a literal anywhere in app/ -
    | the limiter reads whatever is here, and an unknown plan falls back to the
    | default rather than throwing.
    |
    | 'requests_per_minute' is the burst guard; 'requests_per_day' is the plan
    | itself. Null means unmetered, which is what an enterprise agreement
    | usually means in practice - the contract is the limit, not the code.
    |
    */

    'plans' => [
        'free' => [
            'label' => 'Free',
            'price_usd_monthly' => 0,
            'requests_per_minute' => 30,
            'requests_per_day' => 1_000,
        ],
        'basic' => [
            'label' => 'Basic',
            'price_usd_monthly' => 19,
            'requests_per_minute' => 120,
            'requests_per_day' => 50_000,
        ],
        'business' => [
            'label' => 'Business',
            'price_usd_monthly' => 99,
            'requests_per_minute' => 600,
            'requests_per_day' => 500_000,
        ],
        'enterprise' => [
            'label' => 'Enterprise',
            'price_usd_monthly' => null,
            'requests_per_minute' => null,
            'requests_per_day' => null,
        ],
    ],

    /*
    | What a caller gets with no key at all. The public API stays open - a rate
    | board nobody can read is not much of a rate board - but anonymous callers
    | share a much smaller allowance, keyed by IP.
    */
    'anonymous_plan' => 'free',

    'anonymous' => [
        'requests_per_minute' => 20,
        'requests_per_day' => 500,
    ],

];
