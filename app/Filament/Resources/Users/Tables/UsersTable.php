<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('reviews_count')
                    ->counts('reviews')
                    ->label('Reviews')
                    ->sortable(),
                TextColumn::make('banned_at')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? 'Banned' : 'Active')
                    ->color(fn (?string $state) => $state ? 'danger' : 'success'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('banned_at')
                    ->label('Banned')
                    ->nullable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('toggleBan')
                    ->label(fn ($record) => $record->banned_at ? 'Unban' : 'Ban')
                    ->icon(fn ($record) => $record->banned_at ? 'heroicon-o-check-circle' : 'heroicon-o-no-symbol')
                    ->color(fn ($record) => $record->banned_at ? 'success' : 'danger')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update(['banned_at' => $record->banned_at ? null : now()])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('ban')
                        ->label('Ban selected')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['banned_at' => now()]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('unban')
                        ->label('Unban selected')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['banned_at' => null]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
