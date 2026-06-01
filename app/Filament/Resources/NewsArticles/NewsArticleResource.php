<?php

namespace App\Filament\Resources\NewsArticles;

use App\Filament\Resources\NewsArticles\Pages\ListNewsArticles;
use App\Filament\Resources\NewsArticles\Tables\NewsArticlesTable;
use App\Models\NewsArticle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class NewsArticleResource extends Resource
{
    protected static ?string $model = NewsArticle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?string $navigationLabel = 'Nieuws';

    protected static ?string $modelLabel = 'artikel';

    protected static ?string $pluralModelLabel = 'nieuws';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return NewsArticlesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsArticles::route('/'),
        ];
    }

    // Read-only resource: nieuws wordt alleen door de aggregator aangemaakt.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
