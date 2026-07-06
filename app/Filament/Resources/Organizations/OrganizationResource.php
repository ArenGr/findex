<?php

namespace App\Filament\Resources\Organizations;

use App\Filament\Resources\Organizations\Pages\CreateOrganization;
use App\Filament\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Resources\Organizations\Pages\ListOrganizations;
use App\Filament\Resources\Organizations\Pages\ViewOrganization;
use App\Filament\Resources\Organizations\RelationManagers\BranchesRelationManager;
use App\Filament\Resources\Organizations\RelationManagers\CurrencyRatesRelationManager;
use App\Filament\Resources\Organizations\RelationManagers\MortgageOffersRelationManager;
use App\Filament\Resources\Organizations\RelationManagers\ReportRequestsRelationManager;
use App\Filament\Resources\Organizations\RelationManagers\ReviewsRelationManager;
use App\Filament\Resources\Organizations\RelationManagers\SourcesRelationManager;
use App\Filament\Resources\Organizations\Schemas\OrganizationForm;
use App\Filament\Resources\Organizations\Schemas\OrganizationInfolist;
use App\Filament\Resources\Organizations\Tables\OrganizationsTable;
use App\Models\Organization;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Organizations';

    /**
     * Organization::getRouteKeyName() returns 'slug' for the public site's
     * URLs - without this override, Filament would inherit that and try to
     * resolve its own {record} parameter (the numeric id) as a slug lookup.
     */
    protected static ?string $recordRouteKeyName = 'id';

    public static function form(Schema $schema): Schema
    {
        return OrganizationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrganizationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrganizationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            BranchesRelationManager::class,
            ReviewsRelationManager::class,
            ReportRequestsRelationManager::class,
            CurrencyRatesRelationManager::class,
            MortgageOffersRelationManager::class,
            SourcesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganizations::route('/'),
            'create' => CreateOrganization::route('/create'),
            'view' => ViewOrganization::route('/{record}'),
            'edit' => EditOrganization::route('/{record}/edit'),
        ];
    }
}
