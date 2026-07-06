<?php

namespace App\Filament\Resources\Organizations\RelationManagers;

use App\Enums\RateType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CurrencyRatesRelationManager extends RelationManager
{
    protected static string $relationship = 'currencyRates';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('currency_id')
                    ->relationship('currency', 'name')
                    ->required(),
                Select::make('rate_type')
                    ->options(RateType::class)
                    ->default('cash')
                    ->required(),
                TextInput::make('buy_rate')
                    ->required()
                    ->numeric(),
                TextInput::make('sell_rate')
                    ->required()
                    ->numeric(),
                TextInput::make('source_url')
                    ->url(),
                DateTimePicker::make('scraped_at'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
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
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
