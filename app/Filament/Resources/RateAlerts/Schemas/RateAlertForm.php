<?php

namespace App\Filament\Resources\RateAlerts\Schemas;

use App\Enums\RateType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class RateAlertForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('currency_id')
                    ->relationship('currency', 'code')
                    ->required(),
                Select::make('organization_id')
                    ->relationship('organization', 'name')
                    ->helperText('Empty means "any active organization".'),
                Select::make('rate_type')
                    ->options(RateType::class)
                    ->required(),
                Select::make('rate_field')
                    ->options([
                        'buy_rate' => 'Buy rate',
                        'sell_rate' => 'Sell rate',
                    ])
                    ->required(),
                Select::make('direction')
                    ->options([
                        'above' => 'Above',
                        'below' => 'Below',
                    ])
                    ->required(),
                TextInput::make('threshold')
                    ->required()
                    ->numeric(),
                Select::make('channel')
                    ->options([
                        'email' => 'Email',
                        'telegram' => 'Telegram',
                    ])
                    ->required()
                    ->live(),
                TextInput::make('telegram_chat_id')
                    ->required(fn (Get $get) => $get('channel') === 'telegram')
                    ->visible(fn (Get $get) => $get('channel') === 'telegram'),
                Toggle::make('is_active'),
            ]);
    }
}
