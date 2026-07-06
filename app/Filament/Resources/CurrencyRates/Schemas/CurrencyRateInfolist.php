<?php

namespace App\Filament\Resources\CurrencyRates\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CurrencyRateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('organization.name')
                    ->label('Organization'),
                TextEntry::make('currency.name')
                    ->label('Currency'),
                TextEntry::make('rate_type')
                    ->badge(),
                TextEntry::make('buy_rate')
                    ->numeric(),
                TextEntry::make('sell_rate')
                    ->numeric(),
                TextEntry::make('source_url')
                    ->placeholder('-'),
                TextEntry::make('scraped_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
