<?php

namespace App\Filament\Resources\ScrapingJobs;

use App\Filament\Resources\ScrapingJobs\Pages\ListScrapingJobs;
use App\Filament\Resources\ScrapingJobs\Pages\ViewScrapingJob;
use App\Filament\Resources\ScrapingJobs\RelationManagers\LogsRelationManager;
use App\Filament\Resources\ScrapingJobs\Schemas\ScrapingJobInfolist;
use App\Filament\Resources\ScrapingJobs\Tables\ScrapingJobsTable;
use App\Models\ScrapingJob;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ScrapingJobResource extends Resource
{
    protected static ?string $model = ScrapingJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Rates & Scraping';

    /**
     * Jobs are only ever created by the scrape:rates command - the panel
     * offers visibility (view/delete for cleanup) but never authoring.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return ScrapingJobInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ScrapingJobsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScrapingJobs::route('/'),
            'view' => ViewScrapingJob::route('/{record}'),
        ];
    }
}
