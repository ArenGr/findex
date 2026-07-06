<?php

namespace App\Filament\Resources\ScrapingJobs\Pages;

use App\Filament\Resources\ScrapingJobs\ScrapingJobResource;
use Filament\Resources\Pages\ListRecords;

class ListScrapingJobs extends ListRecords
{
    protected static string $resource = ScrapingJobResource::class;
}
