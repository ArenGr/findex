<?php

namespace App\Console\Commands;

use App\Mail\RateAlertTriggered;
use App\Models\CurrencyRate;
use App\Models\RateAlert;
use App\Services\Telegram\TelegramClient;
use App\Services\Viber\ViberClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckRateAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alerts:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check active rate alerts against the latest rates and notify users whose threshold is newly crossed';

    public function handle(TelegramClient $telegram, ViberClient $viber): int
    {
        $alerts = RateAlert::active()->with(['user', 'currency', 'organization'])->get();

        foreach ($alerts as $alert) {
            $matchingRate = $this->findMatchingRate($alert);
            $isMet = $matchingRate !== null;

            if ($isMet && !$alert->is_currently_met) {
                try {
                    $this->notify($telegram, $viber, $alert, $matchingRate);
                    $alert->last_triggered_at = now();
                    $alert->is_currently_met = true;
                } catch (\Throwable $e) {
                    $this->error("Failed to notify alert #{$alert->id}: {$e->getMessage()}");
                }
            } elseif (!$isMet) {
                $alert->is_currently_met = false;
            }

            $alert->save();
        }

        return self::SUCCESS;
    }

    /**
     * The best (first) currently-tracked rate that satisfies the alert, if
     * any. When the alert isn't pinned to one organization, this checks
     * across every active organization's rate for that currency/type.
     */
    private function findMatchingRate(RateAlert $alert): ?CurrencyRate
    {
        $query = CurrencyRate::query()
            ->where('currency_id', $alert->currency_id)
            ->where('rate_type', $alert->rate_type)
            ->whereHas('organization', fn ($query) => $query->active())
            ->with('organization');

        if ($alert->organization_id) {
            $query->where('organization_id', $alert->organization_id);
        }

        return $query->get()->first(fn ($rate) => $alert->isMetBy((float) $rate->{$alert->rate_field}));
    }

    private function notify(TelegramClient $telegram, ViberClient $viber, RateAlert $alert, CurrencyRate $rate): void
    {
        if ($alert->channel === 'telegram') {
            $response = $telegram->sendMessage($alert->telegram_chat_id, $this->messageText($alert, $rate, html: true));

            // Telegram reports delivery failure via ok:false in a 200
            // response (e.g. the user never opened a DM with the bot),
            // not an HTTP error - without this check it looks delivered.
            if (($response['ok'] ?? null) === false) {
                throw new \RuntimeException($response['description'] ?? 'Telegram send failed');
            }

            return;
        }

        if ($alert->channel === 'viber') {
            $response = $viber->sendMessage($alert->viber_chat_id, $this->messageText($alert, $rate, html: false));

            if (($response['ok'] ?? null) === false) {
                throw new \RuntimeException('Viber send failed');
            }

            return;
        }

        Mail::to($alert->user->email)->send(new RateAlertTriggered($alert, $rate));
    }

    private function messageText(RateAlert $alert, CurrencyRate $rate, bool $html): string
    {
        $fieldLabel = $alert->rate_field === 'buy_rate' ? 'Buy' : 'Sell';
        $value = $rate->{$alert->rate_field};

        if (!$html) {
            return "Findex rate alert\n"
                . "{$alert->currency->code} {$fieldLabel} rate is now {$value} at {$rate->organization->name}\n"
                . "Your alert: {$fieldLabel} {$alert->direction} {$alert->threshold}";
        }

        return "<b>Findex rate alert</b>\n"
            . "{$alert->currency->code} {$fieldLabel} rate is now <b>{$value}</b> at {$rate->organization->name}\n"
            . "<i>Your alert: {$fieldLabel} {$alert->direction} {$alert->threshold}</i>";
    }
}
