<?php

namespace App\Http\Controllers;

use App\Services\Telegram\ExchangePartnerReplyHandler;
use App\Services\Telegram\PartnerReplyHandler;
use App\Services\Telegram\RatesBotHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Receives updates Telegram pushes to us (see TelegramClient::setWebhook and
 * the telegram:webhook command), as an alternative to running telegram:poll
 * as a permanently-supervised background process. A normal HTTP route works
 * on any deployment target with no extra infrastructure.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        PartnerReplyHandler $partnerHandler,
        ExchangePartnerReplyHandler $exchangePartnerHandler,
        RatesBotHandler $ratesHandler
    ): Response {
        $secret = config('services.telegram.webhook_secret');

        // Nothing may run before this check. The route is public and
        // unauthenticated, so anything above it - a dump, a log line, an
        // early return - executes for every anonymous caller.
        if (! $secret || ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        try {
            $update = $request->json()->all();

            // Tourism partner connect links and quote-request replies take
            // priority (connect is type-agnostic, so exchange orgs already
            // go through it too); exchange quote declines are tried next;
            // anything left over falls through to the general currency-rate
            // assistant.
            if (! $partnerHandler->handleUpdate($update) && ! $exchangePartnerHandler->handleUpdate($update)) {
                $ratesHandler->handleUpdate($update);
            }
        } catch (\Throwable $e) {
            // Always acknowledge with 2xx regardless: a non-2xx response
            // makes Telegram retry the same update, and repeated failures
            // can get the webhook auto-disabled.
            Log::error('Telegram webhook handling failed: '.$e->getMessage(), ['exception' => $e]);
        }

        return response()->noContent();
    }
}
