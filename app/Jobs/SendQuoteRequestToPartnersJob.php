<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Services\Telegram\TelegramClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendQuoteRequestToPartnersJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public QuoteRequest $quoteRequest)
    {
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(TelegramClient $telegram): void
    {
        $partners = Organization::active()
            ->where('type', 'tourism')
            ->whereNotNull('telegram_chat_id')
            ->whereHas('tourismDestinations', fn ($query) => $query->where(
                'country_code',
                $this->quoteRequest->destination_country
            ))
            ->get();

        $message = $this->buildMessage();

        foreach ($partners as $partner) {
            $response = $telegram->sendMessage($partner->telegram_chat_id, $message);

            if (($response['ok'] ?? null) === false) {
                Log::warning('Quote request Telegram send failed', [
                    'quote_request_id' => $this->quoteRequest->id,
                    'organization_id' => $partner->id,
                    'description' => $response['description'] ?? null,
                ]);

                continue;
            }

            QuoteResponse::create([
                'quote_request_id' => $this->quoteRequest->id,
                'organization_id' => $partner->id,
                'telegram_message_id' => $response['result']['message_id'] ?? null,
            ]);
        }
    }

    /**
     * Written in Armenian regardless of the requester's own site language -
     * these messages go to local Armenian travel agencies, not the tourist
     * who filed the request.
     */
    private function buildMessage(): string
    {
        $r = $this->quoteRequest;

        $extras = collect([
            $r->all_inclusive ? __('tourism.telegram.all_inclusive', [], 'hy') : null,
            $r->insurance ? __('tourism.telegram.insurance', [], 'hy') : null,
        ])->filter()->implode(', ');

        return __('tourism.telegram.request_message', [
            'destination' => __('destinations.' . $r->destination_country, [], 'hy'),
            'hotel' => $r->hotel_name ?: __('tourism.telegram.any_hotel', [], 'hy'),
            'check_in' => $r->check_in->locale('hy')->translatedFormat('d F Y'),
            'check_out' => $r->check_out->locale('hy')->translatedFormat('d F Y'),
            'adults' => $r->adults,
            'children' => $r->children,
            'extras' => $extras !== '' ? $extras : __('tourism.telegram.no_extras', [], 'hy'),
            'notes' => $r->notes ?: '-',
        ], 'hy');
    }
}
