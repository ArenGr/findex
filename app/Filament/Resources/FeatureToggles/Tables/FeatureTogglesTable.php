<?php

namespace App\Filament\Resources\FeatureToggles\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class FeatureTogglesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->label('Page')
                    ->formatStateUsing(fn (string $state) => __('offers.categories.'.$state.'.title'))
                    ->description(fn ($record) => url('/'.app()->getLocale().'/banks/'.$record->key))
                    ->searchable(),

                ToggleColumn::make('is_enabled')
                    ->label('Enabled'),

                TextColumn::make('updated_at')
                    ->label('Last changed')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('key')
            ->paginated(false)
            ->recordActions([])
            ->toolbarActions([]);
    }
}
