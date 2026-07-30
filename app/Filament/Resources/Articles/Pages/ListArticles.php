<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use Filament\Resources\Pages\ListRecords;

class ListArticles extends ListRecords
{
    protected static string $resource = ArticleResource::class;

    /**
     * No CreateAction - articles are authored by writers, not admins, and
     * there's no 'create' page registered in ArticleResource::getPages().
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
