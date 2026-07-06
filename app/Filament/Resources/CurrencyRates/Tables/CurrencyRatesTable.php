<?php

namespace App\Filament\Resources\CurrencyRates\Tables;

use App\Enums\RateType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CurrencyRatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('organization.name')
                    ->searchable(),
                TextColumn::make('currency.name')
                    ->searchable(),
                TextColumn::make('rate_type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('buy_rate')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sell_rate')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('source_url')
                    ->searchable(),
                TextColumn::make('scraped_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('organization_id')
                    ->label('Organization')
                    ->relationship('organization', 'name')
                    ->searchable(),
                SelectFilter::make('currency_id')
                    ->label('Currency')
                    ->relationship('currency', 'code')
                    ->searchable(),
                SelectFilter::make('rate_type')
                    ->options(RateType::class),
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
