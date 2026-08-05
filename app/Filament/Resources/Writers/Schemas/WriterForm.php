<?php

namespace App\Filament\Resources\Writers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class WriterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                Textarea::make('expertise')
                    ->columnSpanFull(),
                TextInput::make('topics')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Approved')
                    ->helperText('Controls whether this writer can publish.'),
            ]);
    }
}
