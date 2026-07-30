<?php

namespace App\Filament\Resources\Articles\Tables;

use App\Enums\ArticleStatus;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('writer.name')
                    ->label('Writer')
                    ->searchable(),
                TextColumn::make('language')
                    ->badge(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(ArticleStatus::class),
                SelectFilter::make('language'),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === ArticleStatus::SUBMITTED)
                    ->action(function ($record) {
                        $record->update([
                            'status' => ArticleStatus::APPROVED,
                            'published_at' => now(),
                            'reviewed_by' => auth()->id(),
                            'rejection_reason' => null,
                        ]);

                        Notification::make()->title('Article approved')->success()->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === ArticleStatus::SUBMITTED)
                    ->schema([
                        Textarea::make('rejection_reason')
                            ->label('Reason for rejection')
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status' => ArticleStatus::REJECTED,
                            'rejection_reason' => $data['rejection_reason'],
                            'reviewed_by' => auth()->id(),
                        ]);

                        Notification::make()->title('Article rejected')->danger()->send();
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
