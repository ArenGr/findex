<?php

namespace App\Filament\Resources\RateAlerts\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class RateAlertInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user.name')
                    ->label('User'),
                TextEntry::make('user.email')
                    ->label('User email'),
                TextEntry::make('currency.code')
                    ->label('Currency'),
                TextEntry::make('organization.name')
                    ->label('Organization')
                    ->placeholder('Any active organization'),
                TextEntry::make('rate_type')
                    ->badge(),
                TextEntry::make('rate_field')
                    ->badge(),
                TextEntry::make('direction')
                    ->badge(),
                TextEntry::make('threshold')
                    ->numeric(),
                TextEntry::make('channel')
                    ->badge(),
                TextEntry::make('telegram_chat_id')
                    ->placeholder('-'),
                IconEntry::make('is_active')
                    ->boolean(),
                IconEntry::make('is_currently_met')
                    ->boolean(),
                TextEntry::make('last_triggered_at')
                    ->dateTime()
                    ->placeholder('Never'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
