<?php

namespace App\Filament\Resources\ScrapingJobs\Pages;

use App\Filament\Resources\ScrapingJobs\ScrapingJobResource;
use App\Jobs\RunAllScrapersJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListScrapingJobs extends ListRecords
{
    protected static string $resource = ScrapingJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runAllScrapers')
                ->label('Run All Scrapers')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Run all scrapers now?')
                ->modalDescription('Fetches live rates and mortgage offers from every active bank, same as the daily scheduled run. Runs in the background and can take a few minutes - the table below (auto-refreshing) will update as each organization finishes.')
                ->action(function () {
                    RunAllScrapersJob::dispatch();

                    Notification::make()
                        ->title('Scraping started')
                        ->body('The table below refreshes automatically as jobs complete.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
