<?php

namespace App\Filament\Resources\NewsArticles\Tables;

use App\Models\Company;
use App\Models\NewsArticle;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class NewsArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Titel')
                    ->wrap()
                    ->limit(90)
                    ->searchable()
                    ->description(fn (NewsArticle $record): ?string => $record->description ? (string) str($record->description)->limit(120) : null),
                TextColumn::make('source')
                    ->label('Bron')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('companies.ticker')
                    ->label('Bedrijven')
                    ->badge()
                    ->color('success')
                    ->placeholder('—'),
                TextColumn::make('published_at')
                    ->label('Gepubliceerd')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('source')
                    ->label('Bron')
                    ->options(fn (): array => NewsArticle::query()
                        ->distinct()
                        ->orderBy('source')
                        ->pluck('source', 'source')
                        ->all()),
                SelectFilter::make('company')
                    ->label('Bedrijf')
                    ->options(fn (): array => Company::query()->orderBy('ticker')->pluck('ticker', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => ! empty($data['value'])
                        ? $query->whereHas('companies', fn (Builder $q) => $q->whereKey($data['value']))
                        : $query),
                Filter::make('period')
                    ->schema([
                        Select::make('range')
                            ->label('Periode')
                            ->options([
                                '24h' => 'Laatste 24 uur',
                                '7d' => 'Laatste 7 dagen',
                                '30d' => 'Laatste 30 dagen',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $since = match ($data['range'] ?? null) {
                            '24h' => Carbon::now()->subDay(),
                            '7d' => Carbon::now()->subWeek(),
                            '30d' => Carbon::now()->subDays(30),
                            default => null,
                        };

                        return $since ? $query->where('published_at', '>=', $since) : $query;
                    }),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Openen')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (NewsArticle $record): string => $record->url, shouldOpenInNewTab: true),
            ]);
    }
}
