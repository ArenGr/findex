<?php

namespace App\Filament\Resources\FeatureToggles\Pages;

use App\Filament\Resources\FeatureToggles\FeatureToggleResource;
use App\Models\FeatureToggle;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListFeatureToggles extends ListRecords
{
    protected static string $resource = FeatureToggleResource::class;

    // Toggles are declared in code, not created here. Sync adds rows for flags
    // a deploy introduced, so the list never lags behind the registry.
    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('Sync from code')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    $added = FeatureToggle::sync();

                    Notification::make()
                        ->title($added === 0 ? 'Already up to date' : "Added {$added} feature toggle(s)")
                        ->success()
                        ->send();
                }),
        ];
    }
}
