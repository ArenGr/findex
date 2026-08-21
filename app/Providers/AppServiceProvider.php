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
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use SocialiteProviders\Apple\Provider as AppleProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ReportAnalyzerInterface::class, LlmReportAnalyzer::class);

        $this->app->singleton(TelegramClient::class, fn () => new TelegramClient(config('services.telegram.bot_token')));

        // The only channel implemented so far - swap or add to this binding
        // (e.g. a composite notifier trying WhatsApp/email too) without
        // touching SendQuoteRequestToPartnersJob, which only knows about
        // the interface.
        $this->app->bind(PartnerNotifierInterface::class, TelegramPartnerNotifier::class);

        // Same binding pattern, same Telegram bot, for the currency
        // exchange quote flow - see SendExchangeQuoteToPartnersJob.
        $this->app->bind(ExchangeNotifierInterface::class, TelegramExchangeNotifier::class);

        // Auto-insurance quotes come from one source: Sil's calculator
        // returns the whole Bureau premium table in a single call, mapped to
        // our insurers by icId (see SilMarketQuoteSource / AutoInsurance
        // QuoteService). The per-insurer direct providers (IngoAppaProvider,
        // ArmeniaInsuranceProvider) and the factory/mock remain in the
        // codebase but are no longer wired into the live flow.
        //
        // Wrapped in a cache so a refresh or a retry does not re-hit Sil -
        // the one thing worth shielding now that it is the sole source. Keyed
        // on a hash of the pricing inputs, never the inputs themselves.
        $this->app->bind(MarketQuoteSourceInterface::class, function ($app) {
            return new CachedMarketQuoteSource(
                $app->make(SilMarketQuoteSource::class),
                $app->make(CacheRepository::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Constrain {locale} globally, once, instead of repeating
        // whereIn('locale', array_keys(config('localization.available')))
        // on every locale-prefixed route group. Adding a language becomes a
        // config-only change (plus re-running route:cache on deploy, since
        // this value gets baked into the cached route table).
        Route::pattern(
            'locale',
            implode('|', array_map(
                fn (string $code) => preg_quote($code, '/'),
                array_keys(config('localization.available')),
            )),
        );

        // Unset by default (trusts nothing extra, Laravel's own default).
        // If deployed behind a reverse proxy, load balancer, or a local
        // tunnel (cloudflared/ngrok), set TRUSTED_PROXIES to its IP(s)/CIDR
        // (comma-separated) or '*' to trust the immediate connecting proxy -
        // otherwise scheme/IP detection misbehaves (HTTPS redirects, mixed
        // content on generated asset URLs, IP-based rate limiting) since
        // every request appears to come from the proxy itself.
        //
        // This has to live here rather than bootstrap/app.php's
        // withMiddleware() closure: that closure runs via
        // afterResolving(HttpKernel::class), which fires before the
        // kernel's own bootstrap() has loaded .env - env() there is always
        // null regardless of what's actually set.
        if ($trustedProxies = env('TRUSTED_PROXIES')) {
            TrustProxies::at($trustedProxies === '*' ? '*' : array_map('trim', explode(',', $trustedProxies)));
        }

        // Limits default to their real production values (see
        // config/rate-limits.php), but are overridable per-environment via
        // .env - during the pre-launch testing phase (nginx already
        // restricts the whole site to a handful of allowlisted IPs, so
        // public abuse isn't reachable anyway), prod's own .env can set
        // these RATE_LIMIT_* vars much higher so the small testing team
        // stops tripping them, without touching this file or the tests
        // that assert the real limits.
        //
        // Read via config() rather than a bare env() here deliberately -
        // the deploy pipeline runs `php artisan config:cache`, after which
        // .env is never loaded again for the life of the cache, so a raw
        // env() call in this file would silently keep returning its
        // hardcoded default forever regardless of what .env says. Only
        // env() calls inside config/*.php are captured at cache-build time
        // and actually take effect (see config/rate-limits.php).

        // Shared by both the customer and organization login forms (see
        // routes/web.php). Keyed by email+IP, matching Laravel Fortify's
        // default: throttling by IP alone lets an attacker on shared/NAT'd
        // IPs lock out real users, and by email alone allows a distributed
        // brute force from many IPs.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(config('rate-limits.login_per_minute'))
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));

        // Keyed on IP alone (unlike 'login', which also keys on the
        // submitted email): the point here is to cap how many accounts one
        // source can create, and an attacker picks a fresh email every
        // time, so including it would defeat the limit entirely.
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(config('rate-limits.register_per_hour'))->by($request->ip()));

        // Logged-in reviews are already capped at one per organization by a
        // DB constraint - this mainly guards against a guest submitting
        // reviews across many organizations from a single IP.
        RateLimiter::for('reviews', fn (Request $request) => Limit::perHour(config('rate-limits.reviews_per_hour'))->by($request->ip()));

        // Each submission fans out to every matching partner's Telegram, so
        // this also protects partners from being spammed via one abusive IP.
        RateLimiter::for('quote_requests', fn (Request $request) => Limit::perHour(config('rate-limits.quote_requests_per_hour'))->by($request->ip()));

        // Guards against using the resend form to mass-email arbitrary
        // addresses - the response is identical whether or not a match is
        // found, so this is the only real abuse control here.
        RateLimiter::for('quote_link_resend', fn (Request $request) => Limit::perHour(config('rate-limits.quote_link_resend_per_hour'))->by($request->ip()));

        // The response_token itself is the real access control (a 40-char
        // random string per partner per request) - this is just defense in
        // depth against brute-forcing or spamming the submit endpoint.
        RateLimiter::for('quote_response_submit', fn (Request $request) => Limit::perHour(config('rate-limits.quote_response_submit_per_hour'))->by($request->ip()));

        // Same three limiters, same reasoning, for the currency exchange
        // quote flow (see exchange.php).
        RateLimiter::for('exchange_quote_requests', fn (Request $request) => Limit::perHour(config('rate-limits.exchange_quote_requests_per_hour'))->by($request->ip()));
        RateLimiter::for('exchange_quote_link_resend', fn (Request $request) => Limit::perHour(config('rate-limits.exchange_quote_link_resend_per_hour'))->by($request->ip()));
        RateLimiter::for('exchange_quote_response_submit', fn (Request $request) => Limit::perHour(config('rate-limits.exchange_quote_response_submit_per_hour'))->by($request->ip()));

        // Each hit makes two paid OpenAI calls (Whisper transcription + a
        // GPT extraction pass) - see VoiceTripFillService.
        RateLimiter::for('voice_fill', fn (Request $request) => Limit::perHour(config('rate-limits.voice_fill_per_hour'))->by($request->ip()));

        // Socialite ships Google/Facebook/GitHub/etc. natively but not Apple -
        // this registers the community socialiteproviders/apple driver so
        // Socialite::driver('apple') resolves (see AppleAuthController).
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('apple', AppleProvider::class);
        });
    }
}
