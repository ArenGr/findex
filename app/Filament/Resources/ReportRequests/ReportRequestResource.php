<?php

namespace App\Filament\Resources\ReportRequests;

use App\Filament\Resources\ReportRequests\Pages\EditReportRequest;
use App\Filament\Resources\ReportRequests\Pages\ListReportRequests;
use App\Filament\Resources\ReportRequests\Pages\ViewReportRequest;
use App\Filament\Resources\ReportRequests\Schemas\ReportRequestForm;
use App\Filament\Resources\ReportRequests\Schemas\ReportRequestInfolist;
use App\Filament\Resources\ReportRequests\Tables\ReportRequestsTable;
use App\Models\ReportRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReportRequestResource extends Resource
{
    protected static ?string $model = ReportRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Organizations';

    /**
     * Requests are only ever created from the organization dashboard (which
     * dispatches GenerateReportJob) - creating one here would just insert a
     * row with no job behind it, so that path is disabled. Editing is still
     * allowed, e.g. to manually reset a stuck request back to "pending".
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ReportRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReportRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReportRequestsTable::configure($table);
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
            'index' => ListReportRequests::route('/'),
            'view' => ViewReportRequest::route('/{record}'),
            'edit' => EditReportRequest::route('/{record}/edit'),
        ];
    }
}
