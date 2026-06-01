<?php

namespace App\Services\News\Providers;

use App\Models\Company;
use App\Support\News\NewsArticleData;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Historische bedrijfsnieuws via Finnhub's company-news endpoint:
 *   /company-news?symbol=AAPL&from=YYYY-MM-DD&to=YYYY-MM-DD&token=KEY
 *
 * Anders dan de RSS-feeds levert dit nieuws met een datumbereik, geschikt voor
 * backfill van oudere artikelen. Wordt aangeroepen door `news:backfill`, niet
 * door de reguliere (elke 5 min) ingest — om de rate limit te sparen.
 * Vereist een (gratis) FINNHUB_API_KEY.
 */
class FinnhubCompanyNewsProvider
{
    public function isConfigured(): bool
    {
        return ! empty(config('news.finnhub.key'));
    }

    /**
     * Haal company-news op voor één bedrijf binnen [from, to].
     *
     * @return array<int, NewsArticleData>
     */
    public function fetch(Company $company, CarbonInterface $from, CarbonInterface $to): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            $response = Http::timeout(config('news.timeout', 10))
                ->get(rtrim(config('news.finnhub.base_url'), '/').'/company-news', [
                    'symbol' => $company->ticker,
                    'from' => $from->format('Y-m-d'),
                    'to' => $to->format('Y-m-d'),
                    'token' => config('news.finnhub.key'),
                ]);

            if (! $response->successful()) {
                Log::warning('Finnhub company-news faalde', [
                    'company' => $company->ticker,
                    'status' => $response->status(),
                ]);

                return [];
            }

            return array_values(array_filter(array_map(
                fn (array $item) => $this->toArticle($company, $item),
                $response->json() ?? [],
            )));
        } catch (Throwable $e) {
            Log::warning('Finnhub company-news exception', [
                'company' => $company->ticker,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function toArticle(Company $company, array $item): ?NewsArticleData
    {
        if (empty($item['url']) || empty($item['headline'])) {
            return null;
        }

        return new NewsArticleData(
            source: 'finnhub',
            url: $item['url'],
            title: $item['headline'],
            publishedAt: isset($item['datetime']) && $item['datetime'] > 0
                ? Carbon::createFromTimestamp($item['datetime'])
                : Carbon::now(),
            description: $item['summary'] ?? null,
            imageUrl: $item['image'] ?? null,
            language: 'en',
            externalId: isset($item['id']) ? (string) $item['id'] : null,
            // Tag met de ticker → gegarandeerde koppeling via api_tagged.
            tickers: [$company->ticker],
        );
    }
}
