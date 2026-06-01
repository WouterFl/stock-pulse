<?php

namespace App\Filament\Pages;

use App\Models\Alert;
use App\Models\Company;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;

class Alerts extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?string $navigationLabel = 'Alerts';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.alerts';

    public static function getNavigationBadge(): ?string
    {
        $count = Alert::query()->unread()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    /**
     * Realtime: toon een toast zodra er een nieuwe alert binnenkomt en
     * herlaad de feed. Luistert op het private 'alerts'-channel (SP-22).
     */
    #[On('echo-private:alerts,AlertTriggered')]
    public function onAlertTriggered(array $event): void
    {
        Notification::make()
            ->title(($event['ticker'] ?? '').': '.($event['title'] ?? 'Nieuwe alert'))
            ->body($event['description'] ?? null)
            ->color(match ($event['severity'] ?? 'info') {
                'critical' => 'danger',
                'warning' => 'warning',
                default => 'info',
            })
            ->send();

        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Alert::query()->with('company'))
            ->defaultSort('triggered_at', 'desc')
            ->poll('30s')
            ->columns([
                TextColumn::make('severity')
                    ->label('')
                    ->badge()
                    ->icon(fn (string $state): string => match ($state) {
                        'critical' => 'heroicon-o-exclamation-triangle',
                        'warning' => 'heroicon-o-exclamation-circle',
                        default => 'heroicon-o-information-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'warning' => 'warning',
                        default => 'info',
                    }),
                TextColumn::make('company.ticker')
                    ->label('Bedrijf')
                    ->weight('bold')
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Melding')
                    ->description(fn (Alert $record): string => $record->shortDescription())
                    ->wrap(),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'absolute_threshold' => 'Drempel',
                        'statistical_outlier' => 'Statistisch',
                        'news_spike' => 'Nieuws-spike',
                        default => $state,
                    }),
                TextColumn::make('triggered_at')
                    ->label('Tijd')
                    ->since()
                    ->sortable(),
                TextColumn::make('read_at')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Gelezen' : 'Ongelezen')
                    ->color(fn ($state): string => $state ? 'gray' : 'success'),
            ])
            ->recordClasses(fn (Alert $record): ?string => $record->read_at === null ? 'font-medium' : null)
            ->filters([
                SelectFilter::make('severity')
                    ->label('Severity')
                    ->options(['info' => 'Info', 'warning' => 'Warning', 'critical' => 'Critical']),
                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'absolute_threshold' => 'Drempel',
                        'statistical_outlier' => 'Statistisch',
                        'news_spike' => 'Nieuws-spike',
                    ]),
                SelectFilter::make('company')
                    ->label('Bedrijf')
                    ->options(fn (): array => Company::query()->orderBy('ticker')->pluck('ticker', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => ! empty($data['value'])
                        ? $query->where('company_id', $data['value'])
                        : $query),
                Filter::make('unread')
                    ->label('Alleen ongelezen')
                    ->query(fn (Builder $query): Builder => $query->whereNull('read_at'))
                    ->toggle(),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (Alert $record): string => $record->title)
                    ->modalContent(fn (Alert $record) => view('filament.pages.partials.alert-details', [
                        'alert' => $record->load('newsArticles', 'company'),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Sluiten')
                    ->after(fn (Alert $record) => $record->markAsRead()),
                Action::make('markAsRead')
                    ->label('Markeer gelezen')
                    ->icon('heroicon-o-check')
                    ->color('gray')
                    ->visible(fn (Alert $record): bool => $record->read_at === null)
                    ->action(fn (Alert $record) => $record->markAsRead()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markAsRead')
                        ->label('Markeer als gelezen')
                        ->icon('heroicon-o-check')
                        ->action(fn (Collection $records) => $records->each->markAsRead())
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
