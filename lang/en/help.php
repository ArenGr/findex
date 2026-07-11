<?php

return [
    'title' => 'Help Center',
    'heading' => 'Help Center',
    'intro' => "Find your way around Findex, or jump straight to what you're looking for.",
    'topics' => [
        [
            'title' => 'Comparing Rates & Cards',
            'body' => "Browse live currency, mortgage and bank card offers side by side, ranked so the best option is obvious.",
            'link_label' => 'Browse rates',
            'route' => 'rates.index',
        ],
        [
            'title' => 'Reviews & Ratings',
            'body' => 'Read what real customers say about a bank or organization, and leave your own review - with or without an account.',
            'link_label' => 'Browse organizations',
            'route' => 'organizations.index',
        ],
        [
            'title' => 'Rate Alerts',
            'body' => "Get notified the moment a bank crosses the buy or sell rate you're watching, by email or Telegram.",
            'link_label' => 'Set up an alert',
            'route' => 'alerts.index',
        ],
        [
            'title' => 'Travel Quote Requests',
            'body' => 'Tell us your trip once, and we\'ll ask our partner travel agencies for a quote - no account required.',
            'link_label' => 'Request travel quotes',
            'route' => 'tourism.request',
        ],
        [
            'title' => 'For Banks & Travel Agencies',
            'body' => 'List your organization on Findex, respond to reviews, and receive travel quote requests for the destinations you serve.',
            'link_label' => 'Register your organization',
            'route' => 'org.register',
        ],
    ],
    'still_need_help_heading' => 'Still need help?',
    'still_need_help_body' => 'Check our FAQ for quick answers, or reach out directly - we read every message.',
    'faq_link' => 'Visit the FAQ',
    'contact_link' => 'Contact us',
];
