<?php

namespace App\Services\News\Providers;

use App\Contracts\NewsProvider;
use App\Models\Company;
use App\Support\News\NewsArticleData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Marketaux levert nieuws mét ticker-tagging (entities[].symbol), wat directe
 * koppeling aan bedrijven mogelijk maakt (match_type = api_tagged).
 */
class MarketauxProvider implements NewsProvider
{
    public function name(): string
    {
        return 'marketaux';
    }

    public function isConfigured(): bool
    {
        return ! empty(config('news.marketaux.key'));
    }

    public function fetch(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            // Vraag gericht naar de tickers die we volgen, zodat de tagging relevant is.
            $symbols = Company::query()->active()->pluck('ticker')->take(100)->implode(',');

            $response = Http::timeout(config('news.timeout', 10))
                ->get(rtrim(config('news.marketaux.base_url'), '/').'/news/all', array_filter([
                    'api_token' => config('news.marketaux.key'),
                    'symbols' => $symbols ?: null,
                    'language' => config('news.marketaux.language', 'en'),
                    'limit' => 50,
                ]));

            if (! $response->successful()) {
                return [];
            }

            return array_values(array_filter(array_map(
                fn (array $item) => $this->toArticle($item),
                $response->json('data', []),
            )));
        } catch (Throwable $e) {
            Log::warning('Marketaux ophalen faalde', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function toArticle(array $item): ?NewsArticleData
    {
        if (empty($item['url']) || empty($item['title'])) {
            return null;
        }

        $tickers = collect($item['entities'] ?? [])
            ->pluck('symbol')
            ->filter()
            ->map(fn ($s) => strtoupper((string) $s))
            ->unique()
            ->values()
            ->all();

        return new NewsArticleData(
            source: $this->name(),
            url: $item['url'],
            title: $item['title'],
            publishedAt: $this->parseDate($item['published_at'] ?? null),
            description: $item['description'] ?? $item['snippet'] ?? null,
            imageUrl: $item['image_url'] ?? null,
            language: $item['language'] ?? null,
            externalId: $item['uuid'] ?? null,
            tickers: $tickers,
        );
    }

    private function parseDate(?string $raw): Carbon
    {
        try {
            return $raw ? Carbon::parse($raw) : Carbon::now();
        } catch (Throwable) {
            return Carbon::now();
        }
    }
}
