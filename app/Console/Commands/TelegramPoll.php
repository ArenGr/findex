<?php

namespace App\Console\Commands;

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
    protected $description = 'Long-poll Telegram for bot updates and reply to the rates menu (no public webhook needed)';

    /**
     * Execute the console command.
     */
    public function handle(TelegramClient $telegram, RatesBotHandler $handler): int
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
                    $handler->handleUpdate($update);
                } catch (\Throwable $e) {
                    $this->error("Error handling update {$update['update_id']}: {$e->getMessage()}");
                }
            }
        }
    }
}
