<?php

namespace App\Filament\Resources\RateAlerts\Tables;

use App\Enums\RateType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RateAlertsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.email')
                    ->label('User')
                    ->searchable(),
                TextColumn::make('currency.code')
                    ->label('Currency')
                    ->searchable(),
                TextColumn::make('organization.name')
                    ->label('Organization')
                    ->placeholder('Any')
                    ->searchable(),
                TextColumn::make('rate_field')
                    ->badge(),
                TextColumn::make('direction')
                    ->badge(),
                TextColumn::make('threshold')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('channel')
                    ->badge(),
                IconColumn::make('is_active')
                    ->boolean(),
                IconColumn::make('is_currently_met')
                    ->boolean()
                    ->label('Met now'),
                TextColumn::make('last_triggered_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('currency_id')
                    ->label('Currency')
                    ->relationship('currency', 'code'),
                SelectFilter::make('rate_type')
                    ->options(RateType::class),
                SelectFilter::make('channel')
                    ->options([
                        'email' => 'Email',
                        'telegram' => 'Telegram',
                    ]),
                TernaryFilter::make('is_active'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
