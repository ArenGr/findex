<?php

namespace App\Filament\Resources\FeatureToggles\Pages;

use App\Filament\Resources\FeatureToggles\FeatureToggleResource;
use Filament\Resources\Pages\ListRecords;

class ListFeatureToggles extends ListRecords
{
    protected static string $resource = FeatureToggleResource::class;

    // No header actions: toggles are seeded, not created here.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
