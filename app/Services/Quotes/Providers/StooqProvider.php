<?php

namespace App\Services\Quotes\Providers;

use App\Contracts\QuoteProvider;
use App\Models\Company;
use App\Support\Quotes\QuoteData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gratis CSV-endpoint van stooq.com. Geen API-key nodig.
 *
 * Voorbeeld: https://stooq.com/q/l/?s=aapl.us&f=sd2t2ohlcv&h&e=csv
 * Respons:   Symbol,Date,Time,Open,High,Low,Close,Volume
 */
class StooqProvider implements QuoteProvider
{
    private const EXCHANGE_SUFFIX = [
        'NASDAQ' => '.us',
        'NYSE' => '.us',
        'AMS' => '.nl',
        'LSE' => '.uk',
        'FRA' => '.de',
        'ETR' => '.de',
    ];

    public function name(): string
    {
        return 'stooq';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function fetch(Company $company): ?QuoteData
    {
        $symbol = $this->symbolFor($company);

        try {
            $response = Http::timeout(config('quotes.timeout', 10))
                ->get(config('quotes.stooq.base_url'), [
                    's' => $symbol,
                    'f' => 'sd2t2ohlcv',
                    'h' => '',
                    'e' => 'csv',
                ]);

            if (! $response->successful()) {
                return null;
            }

            $row = $this->parseCsv($response->body());

            if ($row === null || ! isset($row['Close']) || ! is_numeric($row['Close'])) {
                return null;
            }

            return (new QuoteData(
                price: (float) $row['Close'],
                source: $this->name(),
                fetchedAt: Carbon::now(),
                open: is_numeric($row['Open'] ?? null) ? (float) $row['Open'] : null,
                high: is_numeric($row['High'] ?? null) ? (float) $row['High'] : null,
                low: is_numeric($row['Low'] ?? null) ? (float) $row['Low'] : null,
                volume: is_numeric($row['Volume'] ?? null) ? (int) $row['Volume'] : null,
            ))->withDerivedChangePercent();
        } catch (Throwable $e) {
            Log::warning('Stooq quote fetch faalde', [
                'company' => $company->ticker,
                'symbol' => $symbol,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<string, string>|null
     */
    private function parseCsv(string $body): ?array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($body));

        if (count($lines) < 2) {
            return null;
        }

        $header = str_getcsv($lines[0]);
        $values = str_getcsv($lines[1]);

        if (count($header) !== count($values)) {
            return null;
        }

        $row = array_combine($header, $values);

        // Stooq geeft "N/D" terug voor onbekende symbolen.
        if (($row['Close'] ?? 'N/D') === 'N/D') {
            return null;
        }

        return $row;
    }

    private function symbolFor(Company $company): string
    {
        $suffix = self::EXCHANGE_SUFFIX[$company->exchange] ?? '.us';

        return strtolower($company->ticker).$suffix;
    }
}
