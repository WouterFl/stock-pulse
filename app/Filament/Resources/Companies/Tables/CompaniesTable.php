<?php

namespace App\Filament\Resources\Companies\Tables;

use App\Models\Company;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class CompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ticker')
                    ->label('Ticker')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('exchange')
                    ->label('Beurs')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('sector')
                    ->label('Sector')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('latestQuote.price')
                    ->label('Laatste koers')
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state, $record) => $state === null ? '—' : $record->currency.' '.number_format((float) $state, 2)),
                TextColumn::make('latestQuote.change_percent')
                    ->label('Δ%')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : sprintf('%+.2f%%', (float) $state))
                    ->color(fn ($state) => $state === null ? 'gray' : ((float) $state >= 0 ? 'success' : 'danger')),
                ToggleColumn::make('is_active')
                    ->label('Actief'),
                TextColumn::make('polling_interval_seconds')
                    ->label('Interval')
                    ->suffix(' s')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('alert_use_statistical')
                    ->label('2σ')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Aangemaakt')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('exchange')
                    ->label('Beurs')
                    ->options(fn (): array => Company::query()
                        ->whereNotNull('exchange')
                        ->distinct()
                        ->orderBy('exchange')
                        ->pluck('exchange', 'exchange')
                        ->all()),
                SelectFilter::make('sector')
                    ->label('Sector')
                    ->options(fn (): array => Company::query()
                        ->whereNotNull('sector')
                        ->distinct()
                        ->orderBy('sector')
                        ->pluck('sector', 'sector')
                        ->all()),
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('Alle')
                    ->trueLabel('Alleen actief')
                    ->falseLabel('Alleen inactief'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('activate')
                        ->label('Activeren')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('deactivate')
                        ->label('Deactiveren')
                        ->icon('heroicon-o-pause')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('ticker');
    }
}
