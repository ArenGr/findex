<?php

namespace App\Filament\Resources\ReportRequests\Pages;

use App\Filament\Resources\ReportRequests\ReportRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditReportRequest extends EditRecord
{
    protected static string $resource = ReportRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
