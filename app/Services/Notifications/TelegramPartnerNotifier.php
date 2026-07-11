<?php

namespace App\Services\Notifications;

use App\Models\QuoteResponse;
use App\Services\Telegram\TelegramClient;
use Illuminate\Support\Facades\Log;

class TelegramPartnerNotifier implements PartnerNotifierInterface
{
    public function __construct(private readonly TelegramClient $telegram)
    {
    }

    public function notify(QuoteResponse $response): bool
    {
        $organization = $response->organization;

        if (!$organization->telegram_chat_id) {
            return false;
        }

        $result = $this->telegram->sendMessage(
            $organization->telegram_chat_id,
            $this->buildMessage($response),
            inlineKeyboard: [[
                ['text' => __('tourism.telegram.view_and_respond_button', [], 'hy'), 'url' => $response->secureRespondUrl()],
                ['text' => __('tourism.telegram.not_interested_button', [], 'hy'), 'callback_data' => 'decline:' . $response->id],
            ]]
        );

        if (($result['ok'] ?? null) === false) {
            Log::warning('Quote request Telegram notification failed', [
                'quote_response_id' => $response->id,
                'organization_id' => $organization->id,
                'description' => $result['description'] ?? null,
            ]);

            return false;
        }

        $response->update(['telegram_message_id' => $result['result']['message_id'] ?? null]);

        return true;
    }

    /**
     * Written in Armenian regardless of the requester's own site language -
     * this goes to local Armenian travel agencies, not the tourist who
     * filed the request.
     */
    private function buildMessage(QuoteResponse $response): string
    {
        $r = $response->quoteRequest;

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
