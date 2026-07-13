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
                TextInput::make('slug')
                    ->required(),
                Select::make('type')
                    ->options([
                        'bank' => 'Bank',
                        'exchange' => 'Currency Exchange',
                        'insurance' => 'Insurance',
                        'tourism' => 'Tourism Agency',
                        'other' => 'Other',
                    ])
                    ->required(),
                TextInput::make('website')
                    ->url(),
                FileUpload::make('logo')
                    ->image()
                    ->disk('public')
                    ->directory('organizations/logos'),
                // One description per site language (see
                // config('localization.available')) - Organization
                // no longer has a single 'description' column, see
                // 2026_07_14_000001_add_localized_description_to_organizations_table.
                Textarea::make('description_hy')
                    ->label('Description (Armenian)')
                    ->columnSpanFull(),
                Textarea::make('description_en')
                    ->label('Description (English)')
                    ->columnSpanFull(),
                Textarea::make('description_ru')
                    ->label('Description (Russian)')
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
