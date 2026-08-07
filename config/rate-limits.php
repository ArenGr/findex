<?php

// Deliberately a real config file rather than raw env() calls scattered in
// AppServiceProvider/routes: the deploy pipeline runs `php artisan
// config:cache`, which makes Laravel skip loading .env entirely on every
// later request. A bare env() call outside a config/*.php file silently
// falls back to its default in that case, no matter what .env actually
// says - only env() calls made *while building this cached array* (i.e.
// here, read back via config()) see the real value.

return [
    'login_per_minute' => (int) env('RATE_LIMIT_LOGIN_PER_MINUTE', 5),
    'verify_per_minute' => (int) env('RATE_LIMIT_VERIFY_PER_MINUTE', 6),

    // Shared across all three account types (customer/organization/writer).
    // Each registration writes a user row and sends a verification email,
    // so an unlimited endpoint is both a mail-reputation and an
    // account-enumeration lever (the unique:users,email rule makes a
    // validation error confirm an address is registered).
    'register_per_hour' => (int) env('RATE_LIMIT_REGISTER_PER_HOUR', 10),
    'reviews_per_hour' => (int) env('RATE_LIMIT_REVIEWS_PER_HOUR', 5),
    'quote_requests_per_hour' => (int) env('RATE_LIMIT_QUOTE_REQUESTS_PER_HOUR', 5),
    'quote_link_resend_per_hour' => (int) env('RATE_LIMIT_QUOTE_LINK_RESEND_PER_HOUR', 5),
    'quote_response_submit_per_hour' => (int) env('RATE_LIMIT_QUOTE_RESPONSE_SUBMIT_PER_HOUR', 20),
    'exchange_quote_requests_per_hour' => (int) env('RATE_LIMIT_EXCHANGE_QUOTE_REQUESTS_PER_HOUR', 5),
    'exchange_quote_link_resend_per_hour' => (int) env('RATE_LIMIT_EXCHANGE_QUOTE_LINK_RESEND_PER_HOUR', 5),
    'exchange_quote_response_submit_per_hour' => (int) env('RATE_LIMIT_EXCHANGE_QUOTE_RESPONSE_SUBMIT_PER_HOUR', 20),

    // Each request makes two paid OpenAI API calls (transcription +
    // extraction) - kept far tighter than the other per-hour limits above
    // to bound cost from abuse.
    'voice_fill_per_hour' => (int) env('RATE_LIMIT_VOICE_FILL_PER_HOUR', 10),
];
