<?php

namespace App\Filament\Resources\Ads\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AdInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('placement')
                    ->badge(),
                TextEntry::make('side')
                    ->badge(),
                TextEntry::make('advertiser'),
                TextEntry::make('initials')
                    ->placeholder('-'),
                ImageEntry::make('logo')
                    ->disk('public')
                    ->placeholder('-'),
                TextEntry::make('headline'),
                TextEntry::make('body')
                    ->columnSpanFull(),
                TextEntry::make('cta_label'),
                TextEntry::make('href'),
                IconEntry::make('is_active')
                    ->boolean(),
                TextEntry::make('sort_order')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
