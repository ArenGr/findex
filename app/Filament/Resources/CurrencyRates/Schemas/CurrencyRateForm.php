<?php

namespace App\Filament\Resources\CurrencyRates\Schemas;

use App\Enums\RateType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CurrencyRateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('organization_id')
                    ->relationship('organization', 'name')
                    ->required(),
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
}
