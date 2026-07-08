<?php

namespace App\Filament\Resources\RateAlerts;

use App\Filament\Resources\RateAlerts\Pages\EditRateAlert;
use App\Filament\Resources\RateAlerts\Pages\ListRateAlerts;
use App\Filament\Resources\RateAlerts\Pages\ViewRateAlert;
use App\Filament\Resources\RateAlerts\Schemas\RateAlertForm;
use App\Filament\Resources\RateAlerts\Schemas\RateAlertInfolist;
use App\Filament\Resources\RateAlerts\Tables\RateAlertsTable;
use App\Models\RateAlert;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RateAlertResource extends Resource
{
    protected static ?string $model = RateAlert::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|\UnitEnum|null $navigationGroup = 'Rates & Scraping';

    /**
     * Alerts are only ever created by users from the public /alerts page -
     * the panel offers visibility plus edit/delete for support (fixing a bad
     * telegram_chat_id, disabling an abusive or broken alert), not authoring.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return RateAlertForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RateAlertInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RateAlertsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRateAlerts::route('/'),
            'view' => ViewRateAlert::route('/{record}'),
            'edit' => EditRateAlert::route('/{record}/edit'),
        ];
    }
}
