<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramClient;
use Illuminate\Console\Command;

/**
 * Deploy-time step for production: registers (or removes) the webhook so
 * Telegram pushes updates to a normal route instead of requiring
 * telegram:poll to run forever under a supervisor. Run once after each
 * deploy where APP_URL changes, or on first setup.
 */
class TelegramWebhook extends Command
{
    protected $signature = 'telegram:webhook {action=set : set|unset|info}';

    protected $description = 'Register or remove the Telegram bot webhook for this deployment';

    public function handle(TelegramClient $telegram): int
    {
        if (!config('services.telegram.bot_token')) {
            $this->error('TELEGRAM_BOT_TOKEN is not set in .env.');

            return self::FAILURE;
        }

        return match ($this->argument('action')) {
            'set' => $this->set($telegram),
            'unset' => $this->unset($telegram),
            'info' => $this->showInfo($telegram),
            default => $this->invalidAction(),
        };
    }

    private function set(TelegramClient $telegram): int
    {
        $secret = config('services.telegram.webhook_secret');

        if (!$secret) {
            $this->error('TELEGRAM_WEBHOOK_SECRET is not set in .env - generate one first (e.g. `php artisan tinker --execute="echo Str::random(32);"`).');

            return self::FAILURE;
        }

        $url = route('telegram.webhook');

        if (!str_starts_with($url, 'https://')) {
            $this->error("Telegram requires an HTTPS webhook URL, got: {$url}. Check APP_URL.");

            return self::FAILURE;
        }

        $response = $telegram->setWebhook($url, $secret);

        if (!($response['ok'] ?? false)) {
            $this->error('Telegram rejected the webhook: ' . ($response['description'] ?? 'unknown error'));

            return self::FAILURE;
        }

        $this->info("Webhook registered: {$url}");

        return self::SUCCESS;
    }

    private function unset(TelegramClient $telegram): int
    {
        $telegram->deleteWebhook();
        $this->info('Webhook removed. telegram:poll can be used again (e.g. for local development).');

        return self::SUCCESS;
    }

    private function showInfo(TelegramClient $telegram): int
    {
        $this->line(json_encode($telegram->getWebhookInfo(), JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }

    private function invalidAction(): int
    {
        $this->error('Action must be one of: set, unset, info.');

        return self::FAILURE;
    }
}
