<?php

namespace App\Filament\Resources\ReportRequests\Pages;

use App\Filament\Resources\ReportRequests\ReportRequestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewReportRequest extends ViewRecord
{
    protected static string $resource = ReportRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
