<?php

return [
    'heading' => 'My Rate Alerts',
    'subtitle' => 'Get notified by email, Telegram or Viber when a bank\'s exchange rate crosses a threshold you set.',

    'status_created' => 'Alert created. You\'ll be notified once the rate crosses your threshold.',
    'status_deleted' => 'Alert deleted.',
    'status_telegram_disconnected' => 'Telegram disconnected. You can reconnect anytime.',
    'status_viber_connected' => 'Viber connected - alerts will be sent here.',
    'status_viber_disconnected' => 'Viber disconnected. You can reconnect anytime.',

    'no_alerts' => 'You don\'t have any rate alerts yet. Create one below.',
    'any_organization' => 'Any bank',
    'above' => 'above',
    'below' => 'below',
    'active' => 'Active',
    'paused' => 'Paused',
    'pause' => 'Pause',
    'resume' => 'Resume',
    'delete' => 'Delete',

    'existing_heading' => 'Your Alerts',
    'create_heading' => 'Create a New Alert',

    'form' => [
        'currency' => 'Currency',
        'organization' => 'Bank',
        'rate_type' => 'Rate type',
        'rate_field' => 'Rate',
        'direction' => 'Condition',
        'threshold' => 'Threshold',
        'channel' => 'Notify me via',
        'channel_email' => 'Email',
        'channel_telegram' => 'Telegram',
        'channel_viber' => 'Viber',
        'telegram_not_connected_error' => 'Connect your Telegram account first, then try again.',
        'viber_not_connected_error' => 'Connect your Viber account first, then try again.',
        'submit' => 'Create Alert',
    ],

    'telegram_connect' => [
        'connected' => 'Telegram connected - alerts will be sent here.',
        'not_connected' => 'Not connected yet - connect Telegram to receive alerts this way.',
        'connect_button' => 'Connect Telegram',
        'disconnect_button' => 'Disconnect',
        'hint' => "Opens Telegram and starts a chat with our bot - that's how we'll send you rate alerts.",
        'connected_confirmation' => "You're connected! Rate alerts will now arrive here as Telegram messages.",
    ],

    'viber_connect' => [
        'connected' => 'Viber connected - alerts will be sent here.',
        'not_connected' => 'Not connected yet - connect Viber to receive alerts this way.',
        'connect_button' => 'Connect Viber',
        'disconnect_button' => 'Disconnect',
        'hint' => 'Links your account so we can send you rate alerts on Viber.',
    ],

    'email' => [
        'subject' => ':currency rate alert triggered',
        'heading' => 'Your :currency rate alert was triggered',
        'body' => 'The :field rate at :organization is now :value, matching the condition you set.',
        'view_organization' => 'View Organization',
        'footer' => 'You set this alert for :field rate :direction :threshold. ',
        'manage_alerts' => 'Manage your alerts',
    ],
];
