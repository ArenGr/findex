<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Actions\Action;
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
            ->sendToDatabase(User::where('role', UserRole::ADMIN)->get());
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
            ->sendToDatabase(User::where('role', UserRole::ADMIN)->get());
    }

    /**
     * A new partner account (organization, writer, ...) registered inactive
     * and needs an admin to review and approve it - shared by every
     * "Registered<Type>Controller" so each one only supplies its own
     * copy/icon/review link rather than duplicating the notification
     * plumbing (see RegisteredOrganizationController, RegisteredWriterController).
     */
    public static function pendingApproval(string $title, string $body, string $icon, string $reviewUrl): void
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->icon($icon)
            ->actions([
                Action::make('review')->label('Review')->url($reviewUrl),
            ])
            ->sendToDatabase(User::where('role', UserRole::ADMIN)->get());
    }
}
