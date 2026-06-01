<?php

namespace App\Services\News\Providers;

use App\Contracts\NewsProvider;
use App\Support\News\NewsArticleData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Optionele fallback via newsapi.org. Geen ticker-tagging; matching gebeurt
 * later op ticker/naam door de CompanyArticleMatcher.
 */
class NewsApiProvider implements NewsProvider
{
    public function name(): string
    {
        return 'newsapi';
    }

    public function isConfigured(): bool
    {
        return ! empty(config('news.newsapi.key'));
    }

    public function fetch(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            $response = Http::timeout(config('news.timeout', 10))
                ->get(rtrim(config('news.newsapi.base_url'), '/').'/top-headlines', [
                    'apiKey' => config('news.newsapi.key'),
                    'category' => 'business',
                    'language' => 'en',
                    'pageSize' => 50,
                ]);

            if (! $response->successful()) {
                return [];
            }

            return array_values(array_filter(array_map(
                fn (array $item) => $this->toArticle($item),
                $response->json('articles', []),
            )));
        } catch (Throwable $e) {
            Log::warning('NewsAPI ophalen faalde', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function toArticle(array $item): ?NewsArticleData
    {
        if (empty($item['url']) || empty($item['title'])) {
            return null;
        }

        return new NewsArticleData(
            source: $this->name(),
            url: $item['url'],
            title: $item['title'],
            publishedAt: $this->parseDate($item['publishedAt'] ?? null),
            description: $item['description'] ?? null,
            imageUrl: $item['urlToImage'] ?? null,
            language: 'en',
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
