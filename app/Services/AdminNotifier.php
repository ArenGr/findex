<?php

namespace App\Services;

use App\Models\Admin;
use Filament\Notifications\Notification;

/**
 * Small, reusable wrapper around Filament's admin database-notification bell
 * (see AdminPanelProvider::databaseNotifications()) for background
 * processes - scrapers and queued jobs - that have no request/response cycle
 * to flash a toast on, and whose failures would otherwise only be visible by
 * someone manually reading logs or browsing the scraping jobs table.
 */
class AdminNotifier
{
    /**
     * A source responded successfully (no HTTP/parse exception) but the
     * parser extracted zero rows - almost always a sign the bank's markup
     * changed and the parser's selectors no longer match anything, which
     * would otherwise look identical to "nothing changed since last time"
     * with no error anywhere.
     */
    public static function zeroRecordsScraped(string $organizationName, string $sourceType): void
    {
        Notification::make()
            ->title('Scraper returned zero records')
            ->body("{$organizationName}'s {$sourceType} source responded successfully but no records were parsed - the site's markup may have changed.")
            ->warning()
            ->icon('heroicon-o-exclamation-triangle')
            ->sendToDatabase(Admin::all());
    }

    /**
     * A scheduled scraper run had at least one failing organization/source.
     */
    public static function scraperRunFailed(string $summary): void
    {
        Notification::make()
            ->title('Scraper run had failures')
            ->body($summary)
            ->danger()
            ->icon('heroicon-o-exclamation-triangle')
            ->sendToDatabase(Admin::all());
    }
}
