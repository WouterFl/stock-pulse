<?php

namespace App\Filament\Resources\Companies\RelationManagers;

use App\Models\NewsArticle;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only "Nieuws"-tab op de bedrijfsdetailpagina: de laatste 50 gekoppelde artikelen.
 */
class NewsArticlesRelationManager extends RelationManager
{
    protected static string $relationship = 'newsArticles';

    protected static ?string $title = 'Nieuws';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Gerelateerd nieuws')
            ->recordTitleAttribute('title')
            ->modifyQueryUsing(fn (Builder $query) => $query->latest('published_at')->limit(50))
            ->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Titel')
                    ->wrap()
                    ->limit(90)
                    ->searchable(),
                TextColumn::make('source')
                    ->label('Bron')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('pivot.match_type')
                    ->label('Match')
                    ->badge()
                    ->color('info'),
                TextColumn::make('published_at')
                    ->label('Gepubliceerd')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Openen')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (NewsArticle $record): string => $record->url, shouldOpenInNewTab: true),
            ]);
    }
}
