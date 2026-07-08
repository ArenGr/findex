<?php

namespace App\Filament\Resources\MortgageOffers\Tables;

use App\Enums\MortgageRateType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MortgageOffersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('organization.name')
                    ->searchable(),
                TextColumn::make('currency')
                    ->searchable(),
                TextColumn::make('rate_type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('category')
                    ->badge()
                    ->searchable(),
                TextColumn::make('interest_rate_min')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('interest_rate_max')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('term_min_months')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('term_max_months')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('min_down_payment_percent')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('source_url')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                SelectFilter::make('currency')
                    ->options(fn () => \App\Models\MortgageOffer::query()->distinct()->pluck('currency', 'currency')->all()),
                SelectFilter::make('rate_type')
                    ->options(MortgageRateType::class),
                SelectFilter::make('category')
                    ->options(fn () => \App\Models\MortgageOffer::query()->distinct()->pluck('category', 'category')->filter()->all()),
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
