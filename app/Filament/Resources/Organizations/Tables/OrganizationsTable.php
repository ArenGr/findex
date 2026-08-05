<?php

namespace App\Filament\Resources\Organizations\Tables;

use App\Mail\AdminMessageToOrganization;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;

class OrganizationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('type')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Approved' : 'Pending')
                    ->color(fn (bool $state) => $state ? 'success' : 'warning'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'bank' => 'Bank',
                        'exchange' => 'Currency Exchange',
                        'insurance' => 'Insurance',
                        'tourism' => 'Tourism Agency',
                        'other' => 'Other',
                    ]),
                TernaryFilter::make('is_active')
                    ->label('Approved'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('toggleApproval')
                    ->label(fn ($record) => $record->is_active ? 'Suspend' : 'Approve')
                    ->icon(fn ($record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn ($record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update(['is_active' => ! $record->is_active])),
                // Matches ViewOrganization's header action - see the comment there.
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
                    ->action(function ($record, array $data) {
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
                            ->title("Message queued for {$recipients->count()} recipient(s)")
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('approve')
                        ->label('Approve selected')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('suspend')
                        ->label('Suspend selected')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
