<?php

namespace App\Filament\Resources\Ads\Schemas;

use App\Enums\AdPlacement;
use App\Enums\AdSide;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AdForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('placement')
                    ->options(AdPlacement::class)
                    ->required()
                    ->helperText('Which page/section this ad is shown on.'),
                Select::make('side')
                    ->options(AdSide::class)
                    ->default('right')
                    ->required()
                    ->helperText('Which side of the content it sits on (desktop only - stacks below on mobile).'),
                TextInput::make('advertiser')
                    ->required(),
                TextInput::make('initials')
                    ->maxLength(3)
                    ->helperText('Shown in the logo circle when no logo is uploaded, e.g. "EV".'),
                FileUpload::make('logo')
                    ->image()
                    ->disk('public')
                    ->directory('ads/logos'),
                TextInput::make('headline')
                    ->required(),
                Textarea::make('body')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('cta_label')
                    ->label('Button label')
                    ->required(),
                TextInput::make('href')
                    ->label('Link URL')
                    ->url()
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->helperText('Lowest sort order wins when a placement has more than one active ad.'),
                Toggle::make('is_active')
                    ->required()
                    ->default(true),
            ]);
    }
}
