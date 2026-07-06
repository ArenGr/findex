<?php

namespace App\Filament\Resources\MortgageOffers;

use App\Filament\Resources\MortgageOffers\Pages\CreateMortgageOffer;
use App\Filament\Resources\MortgageOffers\Pages\EditMortgageOffer;
use App\Filament\Resources\MortgageOffers\Pages\ListMortgageOffers;
use App\Filament\Resources\MortgageOffers\Pages\ViewMortgageOffer;
use App\Filament\Resources\MortgageOffers\Schemas\MortgageOfferForm;
use App\Filament\Resources\MortgageOffers\Schemas\MortgageOfferInfolist;
use App\Filament\Resources\MortgageOffers\Tables\MortgageOffersTable;
use App\Models\MortgageOffer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MortgageOfferResource extends Resource
{
    protected static ?string $model = MortgageOffer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|\UnitEnum|null $navigationGroup = 'Rates & Scraping';

    public static function form(Schema $schema): Schema
    {
        return MortgageOfferForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MortgageOfferInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MortgageOffersTable::configure($table);
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
            'index' => ListMortgageOffers::route('/'),
            'create' => CreateMortgageOffer::route('/create'),
            'view' => ViewMortgageOffer::route('/{record}'),
            'edit' => EditMortgageOffer::route('/{record}/edit'),
        ];
    }
}
