<?php

namespace App\Filament\Resources\Companies\Widgets;

use App\Models\Company;
use App\Models\Quote;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CompanyQuoteChartWidget extends ChartWidget
{
    public ?Company $record = null;

    protected ?string $heading = 'Koersverloop';

    public ?string $filter = '1d';

    protected ?string $maxHeight = '320px';

    /**
     * Tijdsvensters: label => [duur in seconden | null voor "all", aantal buckets].
     */
    private const RANGES = [
        '1h' => [3600, 60],
        '1d' => [86400, 96],
        '1w' => [604800, 84],
        '1m' => [2592000, 90],
        'all' => [null, 120],
    ];

    protected function getFilters(): ?array
    {
        return [
            '1h' => '1 uur',
            '1d' => '1 dag',
            '1w' => '1 week',
            '1m' => '1 maand',
            'all' => 'Alles',
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        if ($this->record === null) {
            return ['datasets' => [], 'labels' => []];
        }

        [$seconds, $buckets] = self::RANGES[$this->filter] ?? self::RANGES['1d'];

        $query = Quote::query()
            ->where('company_id', $this->record->id)
            ->orderBy('fetched_at');

        if ($seconds !== null) {
            $query->where('fetched_at', '>=', Carbon::now()->subSeconds($seconds));
        }

        $quotes = $query->get(['price', 'fetched_at']);

        if ($quotes->isEmpty()) {
            return ['datasets' => [['label' => 'Koers', 'data' => []]], 'labels' => []];
        }

        $points = $this->downsample($quotes, $buckets);

        return [
            'datasets' => [[
                'label' => "Koers ({$this->record->currency})",
                'data' => $points->pluck('price')->all(),
                'borderColor' => '#10b981',
                'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                'fill' => true,
                'tension' => 0.3,
                'pointRadius' => 0,
            ]],
            'labels' => $points->pluck('label')->all(),
        ];
    }

    /**
     * Downsample naar maximaal $buckets punten: groepeer op tijd-bucket en
     * neem de laatste prijs per bucket. DB-onafhankelijk (in PHP).
     *
     * @param  Collection<int, Quote>  $quotes
     * @return Collection<int, array{label: string, price: float}>
     */
    private function downsample(Collection $quotes, int $buckets): Collection
    {
        $first = $quotes->first()->fetched_at->getTimestamp();
        $last = $quotes->last()->fetched_at->getTimestamp();
        $span = max(1, $last - $first);
        $bucketSize = max(1, (int) ceil($span / $buckets));

        return $quotes
            ->groupBy(fn (Quote $q) => intdiv($q->fetched_at->getTimestamp() - $first, $bucketSize))
            ->map(function (Collection $group) {
                /** @var Quote $latest */
                $latest = $group->last();

                return [
                    'label' => $latest->fetched_at->format('d-m H:i'),
                    'price' => (float) $latest->price,
                ];
            })
            ->values();
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['ticks' => ['callback' => null]],
            ],
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
