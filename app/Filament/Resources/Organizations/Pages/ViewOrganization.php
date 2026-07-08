<?php

namespace App\Filament\Resources\Organizations\Pages;

use App\Filament\Resources\Organizations\OrganizationResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrganization extends ViewRecord
{
    protected static string $resource = OrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Matches OrganizationsTable's row action and EditOrganization's
            // header action - see the comment there.
            Action::make('toggleApproval')
                ->label(fn () => $this->getRecord()->is_active ? 'Suspend' : 'Approve')
                ->icon(fn () => $this->getRecord()->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                ->color(fn () => $this->getRecord()->is_active ? 'danger' : 'success')
                ->requiresConfirmation()
                ->action(function () {
                    $record = $this->getRecord();
                    $record->update(['is_active' => !$record->is_active]);

                    Notification::make()
                        ->title($record->is_active ? 'Organization approved' : 'Organization suspended')
                        ->success()
                        ->send();
                }),
            EditAction::make(),
        ];
    }
}
