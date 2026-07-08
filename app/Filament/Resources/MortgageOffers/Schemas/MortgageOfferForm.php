<?php

namespace App\Filament\Resources\MortgageOffers\Schemas;

use App\Enums\MortgageRateType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MortgageOfferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('organization_id')
                    ->relationship('organization', 'name')
                    ->required(),
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
}
