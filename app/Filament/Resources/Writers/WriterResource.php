<?php

namespace App\Filament\Resources\Writers;

use App\Filament\Resources\Writers\Pages\CreateWriter;
use App\Filament\Resources\Writers\Pages\EditWriter;
use App\Filament\Resources\Writers\Pages\ListWriters;
use App\Filament\Resources\Writers\Pages\ViewWriter;
use App\Filament\Resources\Writers\Schemas\WriterForm;
use App\Filament\Resources\Writers\Schemas\WriterInfolist;
use App\Filament\Resources\Writers\Tables\WritersTable;
use App\Models\Writer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WriterResource extends Resource
{
    protected static ?string $model = Writer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'Writers';

    /**
     * Writer::getRouteKeyName() returns 'slug' for the public site's URLs -
     * without this override, Filament would inherit that and try to resolve
     * its own {record} parameter (the numeric id) as a slug lookup. Mirrors
     * OrganizationResource's identical override for the same reason.
     */
    protected static ?string $recordRouteKeyName = 'id';

    public static function form(Schema $schema): Schema
    {
        return WriterForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WriterInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WritersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWriters::route('/'),
            'create' => CreateWriter::route('/create'),
            'view' => ViewWriter::route('/{record}'),
            'edit' => EditWriter::route('/{record}/edit'),
        ];
    }
}
