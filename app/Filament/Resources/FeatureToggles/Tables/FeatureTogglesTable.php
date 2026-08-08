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
                // The category's own public title, so the row reads the way
                // it does on the site rather than as a raw slug.
                TextColumn::make('key')
                    ->label('Page')
                    ->formatStateUsing(fn (string $state) => __('offers.categories.'.$state.'.title'))
                    ->description(fn ($record) => url('/'.app()->getLocale().'/banks/'.$record->key))
                    ->searchable(),

                // Toggled inline - the model's saved() hook busts the cache
                // the header and hub read from, so a flip is live on the
                // next request with no extra step.
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
