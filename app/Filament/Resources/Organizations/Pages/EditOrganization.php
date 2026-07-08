<?php

namespace App\Filament\Resources\Organizations\Pages;

use App\Filament\Resources\Organizations\OrganizationResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditOrganization extends EditRecord
{
    protected static string $resource = OrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // One-click approve/suspend, matching OrganizationsTable's row
            // action - the topbar "Review" notification (see
            // RegisteredOrganizationController) lands here, so approving a
            // pending org shouldn't require finding the "Approved" toggle
            // buried in the form below and remembering to hit Save.
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
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
