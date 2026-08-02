<?php

namespace App\Filament\Resources\Organizations\Pages;

use App\Filament\Resources\Organizations\OrganizationResource;
use App\Mail\AdminMessageToOrganization;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Mail;

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
            // Matches OrganizationsTable's row action - see the comment there.
            Action::make('sendMessage')
                ->label('Message')
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->schema([
                    Select::make('from')
                        ->label('Send as')
                        ->options(collect(config('mail-identities'))->map(fn ($identity) => $identity['label'].' <'.$identity['address'].'>'))
                        ->default('findex-team')
                        ->required(),
                    TextInput::make('subject')->required()->maxLength(150),
                    Textarea::make('body')->label('Message')->required()->rows(6),
                ])
                ->action(function (array $data) {
                    $record = $this->getRecord();
                    $recipients = $record->users->pluck('email');

                    if ($recipients->isEmpty()) {
                        Notification::make()
                            ->title('No recipients')
                            ->body('This organization has no user accounts yet.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $identity = config("mail-identities.{$data['from']}");

                    Mail::to($recipients)->send(new AdminMessageToOrganization(
                        $record,
                        $data['subject'],
                        $data['body'],
                        $identity['address'],
                        $identity['name'],
                    ));

                    Notification::make()
                        ->title("Message sent to {$recipients->count()} recipient(s)")
                        ->success()
                        ->send();
                }),
            EditAction::make(),
        ];
    }
}
