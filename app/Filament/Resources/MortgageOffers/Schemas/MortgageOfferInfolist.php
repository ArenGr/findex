<?php

namespace App\Filament\Resources\MortgageOffers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class MortgageOfferInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('organization.name')
                    ->label('Organization'),
                TextEntry::make('currency'),
                TextEntry::make('rate_type')
                    ->badge(),
                TextEntry::make('category')
                    ->badge()
                    ->placeholder('-'),
                TextEntry::make('interest_rate_min')
                    ->numeric(),
                TextEntry::make('interest_rate_max')
                    ->numeric(),
                TextEntry::make('term_min_months')
                    ->placeholder('-'),
                TextEntry::make('term_max_months')
                    ->placeholder('-'),
                TextEntry::make('min_down_payment_percent')
                    ->placeholder('-'),
                TextEntry::make('min_amount')
                    ->placeholder('-'),
                TextEntry::make('max_amount')
                    ->placeholder('-'),
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
