<?php

use App\Http\Middleware\EnsureOrganizationType;
use App\Http\Middleware\EnsureUserIsNotBanned;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\SetLocale;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Sentry\Laravel\Integration as SentryIntegration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('scrape:rates')->daily()->withoutOverlapping();
        $schedule->command('scrape:mortgages')->daily()->withoutOverlapping();
        // Runs after the daily rate scrape so it checks against fresh rates.
        $schedule->command('alerts:check')->dailyAt('00:30')->withoutOverlapping();

        // Report generation (GenerateReportJob) is queued rather than run
        // inline, so a failed LLM call can retry with backoff instead of
        // failing the org's HTTP request. Rather than requiring a
        // separately-supervised `queue:work` daemon, this piggybacks on the
        // cron -> schedule:run infrastructure the commands above already
        // need: it drains whatever's queued once a minute and exits, so
        // queued jobs are picked up within ~1 minute with no extra
        // deployment step. withoutOverlapping() means a slow run (e.g. a
        // slow LLM call) simply gets picked up again next minute instead of
        // running concurrently. Revisit with a real supervised worker (or
        // Laravel Horizon) if report volume grows enough for the ~1 minute
        // latency or per-run bootstrap cost to matter.
        $schedule->command('queue:work --stop-when-empty --tries=3')->everyMinute()->withoutOverlapping();

        // Prunes currency_rate_history/mortgage_offer_history rows older
        // than config('history.retention_months') - see those models'
        // Prunable implementation.
        $schedule->command('model:prune')->daily();

        // Everything above depends on the server's `* * * * * php artisan
        // schedule:run` cron entry actually existing and firing - if it's
        // ever missing or silently stops after a deploy, scraping/alerts/
        // reports all stop with no symptom anyone would notice. If
        // SCHEDULE_HEARTBEAT_URL is set (e.g. a healthchecks.io/Cronitor/
        // Better Uptime check URL, which just needs a periodic GET), this
        // pings it every run so a missed schedule actually triggers an
        // alert there. A no-op when unset.
        if ($heartbeatUrl = env('SCHEDULE_HEARTBEAT_URL')) {
            $schedule->call(fn () => Http::get($heartbeatUrl))
                ->everyFiveMinutes()
                ->name('schedule-heartbeat')
                ->withoutOverlapping();
        }
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'setlocale' => SetLocale::class,
            'banned' => EnsureUserIsNotBanned::class,
            'org.type' => EnsureOrganizationType::class,
            'role' => EnsureUserRole::class,
        ]);

        // TRUSTED_PROXIES is configured from AppServiceProvider::boot() instead
        // of here - this closure runs (via afterResolving(HttpKernel::class))
        // before the kernel's own bootstrap() has loaded .env, so env() here
        // is always null regardless of what's actually set.

        // Telegram's webhook POST is not a browser request and carries no
        // CSRF token - it's authenticated by its own secret-token header
        // instead (see TelegramWebhookController).
        $middleware->validateCsrfTokens(except: [
            'telegram/webhook',
        ]);

        // Organization dashboard routes use the 'organization' guard; without
        // these, an unauthenticated hit on auth:organization would redirect to
        // the customer login instead of the org login (and vice versa for
        // guest:organization), since Laravel's defaults assume a single guard.
        //
        // Laravel's middleware priority sorting can run Authenticate (which
        // triggers this closure) before the 'setlocale' middleware has had a
        // chance to call URL::defaults(['locale' => ...]), so route() can't
        // rely on that default here - the {locale} route parameter is read
        // directly off the request instead, which is available regardless of
        // middleware order since it's bound during route matching.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->routeIs('org.dashboard.*')
            ? route('org.login', ['locale' => $request->route('locale')])
            : route('login', ['locale' => $request->route('locale')]));

        $middleware->redirectUsersTo(fn (Request $request) => $request->routeIs('org.*')
            ? route('org.dashboard.index', ['locale' => $request->route('locale')])
            : route('home', ['locale' => $request->route('locale')]));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // No-op until SENTRY_LARAVEL_DSN is set (see config/sentry.php) -
        // reports unhandled exceptions with full request/user context,
        // beyond what the 'sentry' log channel alone captures.
        SentryIntegration::handles($exceptions);
    })->create();
