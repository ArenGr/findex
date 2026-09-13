<?php

namespace App\Filament\Resources\Writers\Pages;

use App\Filament\Resources\Writers\WriterResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditWriter extends EditRecord
{
    protected static string $resource = WriterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleApproval')
                ->label(fn () => $this->getRecord()->is_active ? 'Suspend' : 'Approve')
                ->icon(fn () => $this->getRecord()->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                ->color(fn () => $this->getRecord()->is_active ? 'danger' : 'success')
                ->requiresConfirmation()
                ->action(function () {
                    $record = $this->getRecord();
                    $record->update(['is_active' => ! $record->is_active]);

                    Notification::make()
                        ->title($record->is_active ? 'Writer approved' : 'Writer suspended')
                        ->success()
                        ->send();
                }),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
