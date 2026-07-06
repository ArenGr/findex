<?php

namespace App\Providers;

use App\Services\Report\LlmReportAnalyzer;
use App\Services\Report\ReportAnalyzerInterface;
use App\Services\Telegram\TelegramClient;
use Illuminate\Support\ServiceProvider;

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
        //
    }
}
