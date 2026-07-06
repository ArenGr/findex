<?php

namespace App\Filament\Resources\ReportRequests\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ReportRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('organization.name')
                    ->label('Organization'),
                TextEntry::make('branch.name')
                    ->label('Branch')
                    ->placeholder('-'),
                TextEntry::make('period_from')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('period_to')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('error_message')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('report.review_count')
                    ->label('Reviews analyzed')
                    ->placeholder('-'),
                TextEntry::make('report.positive_pct')
                    ->label('Positive %')
                    ->placeholder('-'),
                TextEntry::make('report.neutral_pct')
                    ->label('Neutral %')
                    ->placeholder('-'),
                TextEntry::make('report.negative_pct')
                    ->label('Negative %')
                    ->placeholder('-'),
                TextEntry::make('report.summary')
                    ->label('Summary')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
