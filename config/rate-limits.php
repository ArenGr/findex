<?php

return [
    'login_per_minute' => (int) env('RATE_LIMIT_LOGIN_PER_MINUTE', 5),
    'verify_per_minute' => (int) env('RATE_LIMIT_VERIFY_PER_MINUTE', 6),

    // Shared across all three account types (customer/organization/writer).
    'register_per_hour' => (int) env('RATE_LIMIT_REGISTER_PER_HOUR', 10),
    'reviews_per_hour' => (int) env('RATE_LIMIT_REVIEWS_PER_HOUR', 5),
    'quote_requests_per_hour' => (int) env('RATE_LIMIT_QUOTE_REQUESTS_PER_HOUR', 5),
    'quote_link_resend_per_hour' => (int) env('RATE_LIMIT_QUOTE_LINK_RESEND_PER_HOUR', 5),
    'quote_response_submit_per_hour' => (int) env('RATE_LIMIT_QUOTE_RESPONSE_SUBMIT_PER_HOUR', 20),
    'exchange_quote_requests_per_hour' => (int) env('RATE_LIMIT_EXCHANGE_QUOTE_REQUESTS_PER_HOUR', 5),
    'exchange_quote_link_resend_per_hour' => (int) env('RATE_LIMIT_EXCHANGE_QUOTE_LINK_RESEND_PER_HOUR', 5),
    'exchange_quote_response_submit_per_hour' => (int) env('RATE_LIMIT_EXCHANGE_QUOTE_RESPONSE_SUBMIT_PER_HOUR', 20),

    'voice_fill_per_hour' => (int) env('RATE_LIMIT_VOICE_FILL_PER_HOUR', 10),
];
