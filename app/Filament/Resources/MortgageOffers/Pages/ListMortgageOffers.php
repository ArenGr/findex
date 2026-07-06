<?php

namespace App\Filament\Resources\MortgageOffers\Pages;

use App\Filament\Resources\MortgageOffers\MortgageOfferResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMortgageOffers extends ListRecords
{
    protected static string $resource = MortgageOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
