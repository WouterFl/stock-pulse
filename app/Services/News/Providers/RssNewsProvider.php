<?php

namespace App\Services\News\Providers;

use App\Contracts\NewsProvider;
use App\Services\News\Providers\Concerns\ParsesRss;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Haalt artikelen uit een configureerbare lijst RSS-feeds (config/news.php).
 * Geen API-key nodig.
 */
class RssNewsProvider implements NewsProvider
{
    use ParsesRss;

    public function name(): string
    {
        return 'rss';
    }

    public function isConfigured(): bool
    {
        return ! empty(config('news.feeds'));
    }

    public function fetch(): array
    {
        $articles = [];

        foreach (config('news.feeds', []) as $feed) {
            $articles = array_merge($articles, $this->fetchFeed($feed['source'], $feed['url']));
        }

        return $articles;
    }

    /**
     * @return array<int, NewsArticleData>
     */
    private function fetchFeed(string $source, string $url): array
    {
        try {
            $response = Http::timeout(config('news.timeout', 10))
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (StockPulse)'])
                ->get($url);

            if (! $response->successful()) {
                return [];
            }

            return $this->parseRssItems($source, $response->body());
        } catch (Throwable $e) {
            Log::warning('RSS-feed ophalen faalde', ['source' => $source, 'error' => $e->getMessage()]);

            return [];
        }
    }
}
