<?php

namespace App\Filament\Resources\MortgageOffers\Pages;

use App\Filament\Resources\MortgageOffers\MortgageOfferResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMortgageOffer extends ViewRecord
{
    protected static string $resource = MortgageOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
