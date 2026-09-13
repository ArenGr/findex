<?php

return [

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

    // | What a caller gets with no key at all.
    'anonymous_plan' => 'free',

    'anonymous' => [
        'requests_per_minute' => 20,
        'requests_per_day' => 500,
    ],

];
