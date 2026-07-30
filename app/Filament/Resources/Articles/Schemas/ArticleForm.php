<?php

namespace App\Filament\Resources\Articles\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Unused in practice - ArticleResource has no create/edit page (content is
 * authored by writers, not admins), but Resource still requires a form()
 * implementation. Mirrors ReviewForm's identical situation.
 */
class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                Textarea::make('body')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
