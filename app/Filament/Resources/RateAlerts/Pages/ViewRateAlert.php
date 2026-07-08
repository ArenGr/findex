<?php

namespace App\Filament\Resources\RateAlerts\Pages;

use App\Filament\Resources\RateAlerts\RateAlertResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRateAlert extends ViewRecord
{
    protected static string $resource = RateAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
