<?php

namespace App\Filament\Resources\Articles\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ArticleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('title'),
                TextEntry::make('writer.name')
                    ->label('Writer'),
                TextEntry::make('language'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('excerpt')
                    ->placeholder('-')
                    ->columnSpanFull(),
                ImageEntry::make('featured_image')
                    ->label('Featured image')
                    ->disk('public')
                    ->visible(fn ($record) => filled($record->featured_image))
                    ->columnSpanFull(),
                TextEntry::make('body')
                    ->columnSpanFull()
                    ->prose(),
                TextEntry::make('rejection_reason')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('reviewedBy.name')
                    ->label('Reviewed by')
                    ->placeholder('-'),
                TextEntry::make('published_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
