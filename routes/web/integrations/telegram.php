<?php

use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

// Telegram POSTs here directly (see TelegramWebhookController and the
// telegram:webhook command) - no locale prefix, no CSRF token, since this
// isn't a browser request. Auth is the X-Telegram-Bot-Api-Secret-Token
// header, checked in the controller.
Route::post('/telegram/webhook', TelegramWebhookController::class)->name('telegram.webhook');
