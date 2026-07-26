<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    // client_secret is deliberately left blank - socialiteproviders/apple's
    // Provider generates it itself, fresh on every request, as a short-lived
    // (1 hour) JWT signed from team_id/key_id/private_key below. Nothing to
    // pre-generate or rotate manually (see AppleAuthController).
    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'redirect' => env('APPLE_REDIRECT_URI'),
        'team_id' => env('APPLE_TEAM_ID'),
        'key_id' => env('APPLE_KEY_ID'),
        // Absolute path to the downloaded .p8 key file - see
        // storage/app/private/apple/ (already git-ignored).
        'private_key' => env('APPLE_PRIVATE_KEY'),
    ],

    'llm' => [
        // Left unset until a provider is chosen - LlmReportAnalyzer degrades
        // to an empty summary/themes rather than failing report generation
        // when this is empty.
        'url' => env('LLM_API_URL'),
        'key' => env('LLM_API_KEY'),
        'model' => env('LLM_MODEL'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'group_url' => env('TELEGRAM_GROUP_URL'),
        // Telegram echoes this back on every webhook POST as the
        // X-Telegram-Bot-Api-Secret-Token header - lets the webhook route
        // reject requests that didn't actually come from Telegram. Generate
        // with e.g. `php artisan tinker --execute="echo Str::random(32);"`.
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],

    'whatsapp' => [
        'group_url' => env('WHATSAPP_GROUP_URL'),
    ],

];
