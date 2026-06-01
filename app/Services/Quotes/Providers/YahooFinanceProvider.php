<?php

namespace App\Services\Quotes\Providers;

use App\Contracts\QuoteProvider;
use App\Models\Company;
use App\Support\Quotes\QuoteData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class YahooFinanceProvider implements QuoteProvider
{
    /**
     * Beurs → Yahoo-suffix. NASDAQ/NYSE hebben geen suffix.
     */
    private const EXCHANGE_SUFFIX = [
        'NASDAQ' => '',
        'NYSE' => '',
        'AMS' => '.AS',
        'LSE' => '.L',
        'FRA' => '.F',
        'ETR' => '.DE',
    ];

    public function name(): string
    {
        return 'yahoo';
    }

    public function isConfigured(): bool
    {
        return true; // Geen API-key nodig.
    }

    public function fetch(Company $company): ?QuoteData
    {
        $symbol = $this->symbolFor($company);
        $url = rtrim(config('quotes.yahoo.base_url'), '/').'/'.$symbol;

        try {
            $response = Http::timeout(config('quotes.timeout', 10))
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (StockPulse)'])
                ->get($url, ['interval' => '1d', 'range' => '1d']);

            if (! $response->successful()) {
                return null;
            }

            $meta = $response->json('chart.result.0.meta');

            if (! is_array($meta) || ! isset($meta['regularMarketPrice'])) {
                return null;
            }

            return (new QuoteData(
                price: (float) $meta['regularMarketPrice'],
                source: $this->name(),
                fetchedAt: Carbon::now(),
                previousClose: isset($meta['previousClose']) ? (float) $meta['previousClose'] : (isset($meta['chartPreviousClose']) ? (float) $meta['chartPreviousClose'] : null),
                high: isset($meta['regularMarketDayHigh']) ? (float) $meta['regularMarketDayHigh'] : null,
                low: isset($meta['regularMarketDayLow']) ? (float) $meta['regularMarketDayLow'] : null,
                volume: isset($meta['regularMarketVolume']) ? (int) $meta['regularMarketVolume'] : null,
            ))->withDerivedChangePercent();
        } catch (Throwable $e) {
            Log::warning('Yahoo quote fetch faalde', [
                'company' => $company->ticker,
                'symbol' => $symbol,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function symbolFor(Company $company): string
    {
        $suffix = self::EXCHANGE_SUFFIX[$company->exchange] ?? '';

        return $company->ticker.$suffix;
    }
}
