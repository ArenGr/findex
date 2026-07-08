<?php

namespace App\Providers;

use App\Services\Report\LlmReportAnalyzer;
use App\Services\Report\ReportAnalyzerInterface;
use App\Services\Telegram\TelegramClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ReportAnalyzerInterface::class, LlmReportAnalyzer::class);

        $this->app->singleton(TelegramClient::class, fn () => new TelegramClient(config('services.telegram.bot_token')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Shared by both the customer and organization login forms (see
        // routes/web.php). Keyed by email+IP, matching Laravel Fortify's
        // default: throttling by IP alone lets an attacker on shared/NAT'd
        // IPs lock out real users, and by email alone allows a distributed
        // brute force from many IPs.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('email')) . '|' . $request->ip()));
    }
}
