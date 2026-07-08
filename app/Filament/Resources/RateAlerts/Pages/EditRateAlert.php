<?php

namespace App\Filament\Resources\RateAlerts\Pages;

use App\Filament\Resources\RateAlerts\RateAlertResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRateAlert extends EditRecord
{
    protected static string $resource = RateAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
