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
 * Finnhub /quote endpoint (vereist API-key). Respons:
 * { "c": current, "h": high, "l": low, "o": open, "pc": previous close, "dp": change %, "t": ts }
 */
class FinnhubProvider implements QuoteProvider
{
    public function name(): string
    {
        return 'finnhub';
    }

    public function isConfigured(): bool
    {
        return ! empty(config('quotes.finnhub.key'));
    }

    public function fetch(Company $company): ?QuoteData
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::timeout(config('quotes.timeout', 10))
                ->get(rtrim(config('quotes.finnhub.base_url'), '/').'/quote', [
                    'symbol' => $company->ticker,
                    'token' => config('quotes.finnhub.key'),
                ]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            // Finnhub geeft c=0 terug bij een onbekend symbool.
            if (! is_array($data) || ! isset($data['c']) || (float) $data['c'] === 0.0) {
                return null;
            }

            return (new QuoteData(
                price: (float) $data['c'],
                source: $this->name(),
                fetchedAt: isset($data['t']) && $data['t'] > 0 ? Carbon::createFromTimestamp($data['t']) : Carbon::now(),
                open: isset($data['o']) ? (float) $data['o'] : null,
                high: isset($data['h']) ? (float) $data['h'] : null,
                low: isset($data['l']) ? (float) $data['l'] : null,
                previousClose: isset($data['pc']) ? (float) $data['pc'] : null,
                changePercent: isset($data['dp']) ? (float) $data['dp'] : null,
            ))->withDerivedChangePercent();
        } catch (Throwable $e) {
            Log::warning('Finnhub quote fetch faalde', [
                'company' => $company->ticker,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
