<?php

namespace App\Filament\Resources\Organizations\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OrganizationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('slug'),
                TextEntry::make('type'),
                TextEntry::make('website')
                    ->placeholder('-'),
                TextEntry::make('logo')
                    ->placeholder('-'),
                TextEntry::make('description_hy')
                    ->label('Description (Armenian)')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('description_en')
                    ->label('Description (English)')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('description_ru')
                    ->label('Description (Russian)')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('country_code'),
                IconEntry::make('is_active')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
