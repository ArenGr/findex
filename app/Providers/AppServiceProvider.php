<?php

namespace App\Providers;

use App\Services\Insurance\CachedMarketQuoteSource;
use App\Services\Insurance\MarketQuoteSourceInterface;
use App\Services\Insurance\SilMarketQuoteSource;
use App\Services\Notifications\ExchangeNotifierInterface;
use App\Services\Notifications\PartnerNotifierInterface;
use App\Services\Notifications\TelegramExchangeNotifier;
use App\Services\Notifications\TelegramPartnerNotifier;
use App\Services\Report\LlmReportAnalyzer;
use App\Services\Report\ReportAnalyzerInterface;
use App\Services\Telegram\TelegramClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    // Register any application services.
    public function register(): void
    {
        $this->app->bind(ReportAnalyzerInterface::class, LlmReportAnalyzer::class);

        $this->app->singleton(TelegramClient::class, fn () => new TelegramClient(config('services.telegram.bot_token')));

        // The only channel implemented so far - swap or add to this binding (e.g.
        $this->app->bind(PartnerNotifierInterface::class, TelegramPartnerNotifier::class);

        $this->app->bind(ExchangeNotifierInterface::class, TelegramExchangeNotifier::class);

        $this->app->bind(MarketQuoteSourceInterface::class, function ($app) {
            return new CachedMarketQuoteSource(
                $app->make(SilMarketQuoteSource::class),
                $app->make(CacheRepository::class),
            );
        });
    }

    // Bootstrap any application services.
    public function boot(): void
    {
        Route::pattern(
            'locale',
            implode('|', array_map(
                fn (string $code) => preg_quote($code, '/'),
                array_keys(config('localization.available')),
            )),
        );

        // Unset by default (trusts nothing extra, Laravel's own default).
        if ($trustedProxies = env('TRUSTED_PROXIES')) {
            TrustProxies::at($trustedProxies === '*' ? '*' : array_map('trim', explode(',', $trustedProxies)));
        }

        // Shared by both the customer and organization login forms (see routes/web.php).
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(config('rate-limits.login_per_minute'))
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('register', fn (Request $request) => Limit::perHour(config('rate-limits.register_per_hour'))->by($request->ip()));

        RateLimiter::for('reviews', fn (Request $request) => Limit::perHour(config('rate-limits.reviews_per_hour'))->by($request->ip()));

        RateLimiter::for('quote_requests', fn (Request $request) => Limit::perHour(config('rate-limits.quote_requests_per_hour'))->by($request->ip()));

        RateLimiter::for('quote_link_resend', fn (Request $request) => Limit::perHour(config('rate-limits.quote_link_resend_per_hour'))->by($request->ip()));

        RateLimiter::for('quote_response_submit', fn (Request $request) => Limit::perHour(config('rate-limits.quote_response_submit_per_hour'))->by($request->ip()));

        // Same three limiters, same reasoning, for the currency exchange quote flow (see exchange.php).
        RateLimiter::for('exchange_quote_requests', fn (Request $request) => Limit::perHour(config('rate-limits.exchange_quote_requests_per_hour'))->by($request->ip()));
        RateLimiter::for('exchange_quote_link_resend', fn (Request $request) => Limit::perHour(config('rate-limits.exchange_quote_link_resend_per_hour'))->by($request->ip()));
        RateLimiter::for('exchange_quote_response_submit', fn (Request $request) => Limit::perHour(config('rate-limits.exchange_quote_response_submit_per_hour'))->by($request->ip()));

        RateLimiter::for('voice_fill', fn (Request $request) => Limit::perHour(config('rate-limits.voice_fill_per_hour'))->by($request->ip()));
    }
}
