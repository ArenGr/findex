<?php

return [
    'heading' => 'My Rate Alerts',
    'subtitle' => 'Get notified by email or Telegram when a bank\'s exchange rate crosses a threshold you set.',

    'status_created' => 'Alert created. You\'ll be notified once the rate crosses your threshold.',
    'status_deleted' => 'Alert deleted.',

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
        'telegram_chat_id' => 'Telegram chat ID',
        'telegram_help' => 'Message :bot on Telegram, then paste the chat ID it replies with here.',
        'submit' => 'Create Alert',
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
