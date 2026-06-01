<?php

namespace App\Filament\Resources\Companies\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only overzicht van de meest recente koersen per bedrijf (laatste 20).
 */
class QuotesRelationManager extends RelationManager
{
    protected static string $relationship = 'quotes';

    protected static ?string $title = 'Recente koersen';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Laatste 20 koersen')
            ->modifyQueryUsing(fn (Builder $query) => $query->latest('fetched_at')->limit(20))
            ->defaultSort('fetched_at', 'desc')
            ->paginated(false)
            ->columns([
                TextColumn::make('fetched_at')
                    ->label('Tijdstip')
                    ->dateTime('d-m-Y H:i:s'),
                TextColumn::make('price')
                    ->label('Koers')
                    ->numeric(decimalPlaces: 2)
                    ->formatStateUsing(fn ($state, $record) => $record->company->currency.' '.number_format((float) $state, 2)),
                TextColumn::make('change_percent')
                    ->label('Δ%')
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : sprintf('%+.2f%%', (float) $state))
                    ->color(fn ($state) => $state === null ? 'gray' : ((float) $state >= 0 ? 'success' : 'danger'))
                    ->badge(),
                TextColumn::make('volume')
                    ->label('Volume')
                    ->numeric()
                    ->placeholder('—'),
                TextColumn::make('source')
                    ->label('Bron')
                    ->badge()
                    ->color('gray'),
            ]);
    }
}
