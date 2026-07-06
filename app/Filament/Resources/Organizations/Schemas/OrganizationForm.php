<?php

namespace App\Filament\Resources\Organizations\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class OrganizationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('slug')
                    ->required(),
                Select::make('type')
                    ->options([
                        'bank' => 'Bank',
                        'exchange' => 'Currency Exchange',
                        'insurance' => 'Insurance',
                        'other' => 'Other',
                    ])
                    ->required(),
                TextInput::make('website')
                    ->url(),
                FileUpload::make('logo')
                    ->image()
                    ->disk('public')
                    ->directory('organizations/logos'),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('country_code')
                    ->required()
                    ->default('AM'),
                Toggle::make('is_active')
                    ->label('Approved')
                    ->helperText('Controls whether this organization\'s public page is visible.'),
            ]);
    }
}
