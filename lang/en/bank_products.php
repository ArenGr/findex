<?php

return [
    'sample_badge' => 'Sample data',
    'sample_notice' => 'The figures below are invented examples shown to illustrate the layout. They are not real offers, and no bank named here is a real institution.',

    'needed_heading' => 'What we need from each bank',
    'needed_intro' => 'Send us these fields for every product you want listed, and your offers replace the sample rows above.',

    'cta_heading' => 'Want your products listed here?',
    'cta_body' => 'Get in touch and we will walk you through the format and the update schedule.',
    'cta_button' => 'Contact us',

    'credit-cards' => [
        'intro' => 'Compare annual fees, cashback rates and interest-free periods across every card on the Armenian market, side by side.',
        'columns' => ['Card', 'Annual fee (AMD)', 'Cashback (%)', 'APR (%)', 'Interest-free (days)'],
        'fields' => [
            'Card name and tier',
            'Annual fee in AMD, and any conditions that waive it',
            'Cashback rate, with per-category caps and monthly limits',
            'Annual percentage rate (APR)',
            'Interest-free period, in days',
            'Key perks — lounge access, travel insurance, concierge',
            'Eligibility — minimum income and required documents',
        ],
    ],

    'business-loans' => [
        'intro' => 'Compare financing for Armenian businesses by amount, rate, term and the fees that come with each product.',
        'columns' => ['Product', 'Max amount (AMD)', 'Rate (%)', 'Term (months)', 'Processing fee (%)'],
        'fields' => [
            'Product name and the business type it targets',
            'Minimum and maximum loan amount in AMD',
            'Annual interest rate, and whether it is fixed or floating',
            'Minimum and maximum term in months',
            'Processing fee, plus any early-repayment charge',
            'Collateral and guarantor requirements',
            'Eligibility — trading history, turnover, documents',
        ],
    ],

    'student-loans' => [
        'intro' => 'Compare education financing in Armenia by amount, rate, repayment term and how long you can defer payments.',
        'columns' => ['Product', 'Max amount (AMD)', 'Rate (%)', 'Term (months)', 'Grace period (months)'],
        'fields' => [
            'Product name and what it covers — tuition, living costs, study abroad',
            'Minimum and maximum amount in AMD',
            'Annual interest rate',
            'Repayment term in months',
            'Grace period before repayments begin, in months',
            'Whether a co-signer or guarantor is required',
            'Eligibility — accepted institutions and required documents',
        ],
    ],

    'investing' => [
        'intro' => 'Compare investment and brokerage products from Armenian banks by entry amount, fees and term.',
        'columns' => ['Product', 'Min. deposit (AMD)', 'Management fee (%)', 'Brokerage fee (%)', 'Term (months)'],
        'fields' => [
            'Product name and type — bonds, managed portfolio, brokerage account',
            'Minimum deposit in AMD',
            'Annual management fee',
            'Brokerage or transaction fee per trade',
            'Term in months, or none if open-ended',
            'Which markets and instruments are accessible',
            'Eligibility — investor status and required documents',
        ],
    ],
];
