<?php

namespace App\Console\Commands;

use App\Services\Telegram\PartnerReplyHandler;
use App\Services\Telegram\RatesBotHandler;
use App\Services\Telegram\TelegramClient;
use Illuminate\Console\Command;

class TelegramPoll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:poll';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Long-poll Telegram for bot updates (local development only - production registers a webhook instead, see the telegram:webhook command)';

    /**
     * Execute the console command.
     *
     * Telegram delivers updates via polling or a webhook, never both - if a
     * webhook is currently registered (`telegram:webhook set`), this will
     * fail with a "can't use getUpdates" error from the API until it's
     * removed (`telegram:webhook unset`).
     */
    public function handle(TelegramClient $telegram, PartnerReplyHandler $partnerHandler, RatesBotHandler $ratesHandler): int
    {
        if (!config('services.telegram.bot_token')) {
            $this->error('TELEGRAM_BOT_TOKEN is not set in .env.');

            return self::FAILURE;
        }

        $this->info('Polling Telegram for updates... (Ctrl+C to stop)');

        $offset = 0;

        while (true) {
            $updates = $telegram->getUpdates($offset, timeout: 30);

            foreach ($updates as $update) {
                $offset = $update['update_id'] + 1;

                try {
                    // Tourism partner connect links and quote-request replies
                    // take priority; anything left over falls through to the
                    // general currency-rate assistant - mirrors
                    // TelegramWebhookController's routing.
                    if (!$partnerHandler->handleUpdate($update)) {
                        $ratesHandler->handleUpdate($update);
                    }
                } catch (\Throwable $e) {
                    $this->error("Error handling update {$update['update_id']}: {$e->getMessage()}");
                }
            }
        }
    }
}
