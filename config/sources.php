<?php

return [
    'banks' => [
        'acba' => [
            'name' => 'ACBA Bank',
            'website' => 'https://www.acba.am',
            'endpoints' => [
                'currency_rates' => '/en',
                'deposits'      => '/hy/deposits',
                'loans'         => '/hy/loans',
                'mortgages'     => '/hy/mortgage',
            ],
        ],
        'ineco' => [
            'name' => 'Inecobank',
            'website' => 'https://www.inecobank.am',
            'endpoints' => [
                'currency_rates' => '/exchange-rates',
                'deposits'      => '/deposits',
                'loans'         => '/loans',
            ],
        ],
    ],
];
