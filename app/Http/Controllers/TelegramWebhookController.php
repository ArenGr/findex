<?php

namespace App\Http\Controllers;

use App\Services\Telegram\ExchangePartnerReplyHandler;
use App\Services\Telegram\PartnerReplyHandler;
use App\Services\Telegram\RatesBotHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class TelegramWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        PartnerReplyHandler $partnerHandler,
        ExchangePartnerReplyHandler $exchangePartnerHandler,
        RatesBotHandler $ratesHandler
    ): Response {
        $secret = config('services.telegram.webhook_secret');

        // Nothing may run before this check.
        if (! $secret || ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        try {
            $update = $request->json()->all();

            if (! $partnerHandler->handleUpdate($update) && ! $exchangePartnerHandler->handleUpdate($update)) {
                $ratesHandler->handleUpdate($update);
            }
        } catch (\Throwable $e) {
            Log::error('Telegram webhook handling failed: '.$e->getMessage(), ['exception' => $e]);
        }

        return response()->noContent();
    }
}
