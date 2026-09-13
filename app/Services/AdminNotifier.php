<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class AdminNotifier
{
    public static function zeroRecordsScraped(string $organizationName, string $sourceType): void
    {
        Notification::make()
            ->title('Scraper returned zero records')
            ->body("{$organizationName}'s {$sourceType} source responded successfully but no records were parsed - the site's markup may have changed.")
            ->warning()
            ->icon('heroicon-o-exclamation-triangle')
            ->sendToDatabase(User::where('role', UserRole::ADMIN)->get());
    }

    // A scheduled scraper run had at least one failing organization/source.
    public static function scraperRunFailed(string $summary): void
    {
        Notification::make()
            ->title('Scraper run had failures')
            ->body($summary)
            ->danger()
            ->icon('heroicon-o-exclamation-triangle')
            ->sendToDatabase(User::where('role', UserRole::ADMIN)->get());
    }

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
