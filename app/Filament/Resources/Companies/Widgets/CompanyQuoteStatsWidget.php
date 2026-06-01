<?php

namespace App\Filament\Resources\Companies\Widgets;

use App\Models\Company;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CompanyQuoteStatsWidget extends StatsOverviewWidget
{
    public ?Company $record = null;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $quote = $this->record?->latestQuote;

        if ($quote === null) {
            return [
                Stat::make('Laatste koers', 'Nog geen data')
                    ->description('Wacht op de eerste scrape')
                    ->color('gray'),
            ];
        }

        $change = $quote->change_percent !== null ? (float) $quote->change_percent : null;
        $changeColor = $change === null ? 'gray' : ($change >= 0 ? 'success' : 'danger');
        $changeIcon = $change === null
            ? null
            : ($change >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down');
        $changeLabel = $change === null
            ? 'Geen referentie'
            : sprintf('%+.2f%% t.o.v. vorige close', $change);

        return [
            Stat::make('Laatste koers', $this->record->currency.' '.number_format((float) $quote->price, 2))
                ->description($changeLabel)
                ->descriptionIcon($changeIcon)
                ->color($changeColor),

            Stat::make('Volume', $quote->volume !== null ? number_format((int) $quote->volume) : '—')
                ->description('Laatst gemeten volume')
                ->color('gray'),

            Stat::make('Laatste update', $quote->fetched_at->diffForHumans())
                ->description('Bron: '.$quote->source)
                ->descriptionIcon('heroicon-m-signal')
                ->color('gray'),
        ];
    }

    public static function canView(): bool
    {
        return true;
    }
}
