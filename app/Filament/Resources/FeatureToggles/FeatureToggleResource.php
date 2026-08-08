<?php

namespace App\Filament\Resources\FeatureToggles;

use App\Filament\Resources\FeatureToggles\Pages\ListFeatureToggles;
use App\Filament\Resources\FeatureToggles\Tables\FeatureTogglesTable;
use App\Models\FeatureToggle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Switches each bank product page on or off site-wide. Deliberately
 * list-only: the rows are seeded from OfferController::CATEGORIES
 * (FeatureToggleSeeder) because a toggle is only meaningful if there's a
 * page behind it, so the panel can flip them but not invent or delete
 * them.
 */
class FeatureToggleResource extends Resource
{
    protected static ?string $model = FeatureToggle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Feature Toggles';

    protected static ?string $recordTitleAttribute = 'key';

    public static function table(Table $table): Table
    {
        return FeatureTogglesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeatureToggles::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(FeatureToggle|Model $record): bool
    {
        return false;
    }
}
