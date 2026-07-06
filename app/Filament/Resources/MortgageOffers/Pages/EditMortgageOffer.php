<?php

namespace App\Filament\Resources\MortgageOffers\Pages;

use App\Filament\Resources\MortgageOffers\MortgageOfferResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditMortgageOffer extends EditRecord
{
    protected static string $resource = MortgageOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
