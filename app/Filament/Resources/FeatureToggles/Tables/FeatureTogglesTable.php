<?php

namespace App\Filament\Resources\FeatureToggles\Tables;

use App\Support\Features;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class FeatureTogglesTable
{
    public static function configure(Table $table): Table
    {
        $meta = fn ($record, string $field) => Features::all()[$record->key][$field] ?? null;

        return $table
            // Rows for keys no longer in the registry would have no label and
            // no effect, so they are not listed.
            ->modifyQueryUsing(fn ($query) => $query
                ->whereIn('key', Features::keys())
                // Registry order, so a child always sits under its parent.
                ->orderByRaw('FIELD(`key`'.str_repeat(', ?', count(Features::keys())).')', Features::keys()))
            ->columns([
                TextColumn::make('key')
                    ->label('Feature')
                    ->formatStateUsing(fn ($record) => $meta($record, 'label') ?? $record->key)
                    ->description(fn ($record) => $meta($record, 'description'))
                    ->icon(fn ($record) => $meta($record, 'parent') ? 'heroicon-o-arrow-turn-down-right' : null)
                    ->searchable(),

                ToggleColumn::make('is_enabled')
                    ->label('On'),

                // A child whose parent is off is off whatever its own row says.
                TextColumn::make('effective')
                    ->label('Effective')
                    ->state(fn ($record) => Features::enabled($record->key) ? 'Live' : 'Off')
                    ->badge()
                    ->color(fn (string $state) => $state === 'Live' ? 'success' : 'gray')
                    ->tooltip(fn ($record) => ($parent = $meta($record, 'parent'))
                        && Features::disabled($parent)
                        ? 'Held off by "'.(Features::all()[$parent]['label'] ?? $parent).'"'
                        : null),

                TextColumn::make('updated_at')
                    ->label('Last changed')
                    ->since()
                    ->sortable(),
            ])
            ->groups([
                Group::make('key')
                    ->label('Group')
                    ->getTitleFromRecordUsing(fn ($record) => $meta($record, 'group') ?? 'Other'),
            ])
            ->defaultGroup('key')
            ->paginated(false)
            ->recordActions([])
            ->toolbarActions([]);
    }
}
