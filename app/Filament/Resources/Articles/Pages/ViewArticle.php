<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Enums\ArticleStatus;
use App\Filament\Resources\Articles\ArticleResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewArticle extends ViewRecord
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->getRecord()->status === ArticleStatus::SUBMITTED)
                ->action(function () {
                    $record = $this->getRecord();
                    $record->update([
                        'status' => ArticleStatus::APPROVED,
                        'published_at' => now(),
                        'reviewed_by' => auth()->id(),
                        'rejection_reason' => null,
                    ]);

                    Notification::make()->title('Article approved')->success()->send();
                }),
            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->getRecord()->status === ArticleStatus::SUBMITTED)
                ->schema([
                    Textarea::make('rejection_reason')
                        ->label('Reason for rejection')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $record = $this->getRecord();
                    $record->update([
                        'status' => ArticleStatus::REJECTED,
                        'rejection_reason' => $data['rejection_reason'],
                        'reviewed_by' => auth()->id(),
                    ]);

                    Notification::make()->title('Article rejected')->danger()->send();
                }),
        ];
    }
}
