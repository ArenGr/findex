<?php

namespace App\Filament\Resources\Organizations\RelationManagers;

use App\Enums\MortgageRateType;
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

class MortgageOffersRelationManager extends RelationManager
{
    protected static string $relationship = 'mortgageOffers';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('currency')
                    ->required(),
                Select::make('rate_type')
                    ->options(MortgageRateType::class)
                    ->default('fixed')
                    ->required(),
                Select::make('category')
                    // Only one category is supported so far - the public
                    // mortgage/compare pages filter on it, so it must never
                    // be left empty (see mortgage-offers-table.blade.php).
                    ->options([
                        'secondary_market' => 'Buying an existing home (secondary market)',
                    ])
                    ->default('secondary_market')
                    ->required(),
                TextInput::make('interest_rate_min')
                    ->required()
                    ->numeric(),
                TextInput::make('interest_rate_max')
                    ->required()
                    ->numeric(),
                TextInput::make('term_min_months')
                    ->numeric(),
                TextInput::make('term_max_months')
                    ->numeric(),
                TextInput::make('min_down_payment_percent')
                    ->numeric(),
                TextInput::make('min_amount')
                    ->numeric(),
                TextInput::make('max_amount')
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
