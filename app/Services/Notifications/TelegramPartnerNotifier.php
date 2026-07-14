<?php

namespace App\Services\Notifications;

use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Models\QuoteSuggestion;
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

    public function remind(QuoteResponse $response): bool
    {
        $organization = $response->organization;

        if (!$organization->telegram_chat_id) {
            return false;
        }

        $result = $this->telegram->sendMessage(
            $organization->telegram_chat_id,
            __('tourism.telegram.reminder_message', [], 'hy'),
            inlineKeyboard: [[
                ['text' => __('tourism.telegram.view_and_respond_button', [], 'hy'), 'url' => $response->secureRespondUrl()],
                ['text' => __('tourism.telegram.not_interested_button', [], 'hy'), 'callback_data' => 'decline:' . $response->id],
            ]]
        );

        if (($result['ok'] ?? null) === false) {
            Log::warning('Quote request Telegram reminder failed', [
                'quote_response_id' => $response->id,
                'organization_id' => $organization->id,
                'description' => $result['description'] ?? null,
            ]);

            return false;
        }

        return true;
    }

    public function notifyClaim(QuoteSuggestion $suggestion): bool
    {
        $organization = $suggestion->response->organization;

        if (!$organization->telegram_chat_id) {
            return false;
        }

        $claimant = $suggestion->claimedBy;

        $result = $this->telegram->sendMessage(
            $organization->telegram_chat_id,
            __('tourism.telegram.promo_claimed_message', [
                'code' => $suggestion->promo_code,
                'name' => $claimant->name,
                'email' => $claimant->email,
            ], 'hy')
        );

        if (($result['ok'] ?? null) === false) {
            Log::warning('Promo code claim Telegram notification failed', [
                'quote_suggestion_id' => $suggestion->id,
                'organization_id' => $organization->id,
                'description' => $result['description'] ?? null,
            ]);

            return false;
        }

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
            'budget' => $this->budgetLabel($r),
            'notes' => $r->notes ?: '-',
        ], 'hy');
    }

    private function budgetLabel(QuoteRequest $request): string
    {
        if ($request->budget_min_amd && $request->budget_max_amd) {
            return number_format((float) $request->budget_min_amd).'–'.number_format((float) $request->budget_max_amd).' '.__('tourism.request.amd', [], 'hy');
        }

        if ($request->budget_min_amd) {
            return __('tourism.telegram.budget_at_least', ['amount' => number_format((float) $request->budget_min_amd)], 'hy');
        }

        if ($request->budget_max_amd) {
            return __('tourism.telegram.budget_up_to', ['amount' => number_format((float) $request->budget_max_amd)], 'hy');
        }

        return __('tourism.telegram.budget_not_specified', [], 'hy');
    }
}
