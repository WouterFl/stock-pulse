<?php

namespace App\Services\News\Providers;

use App\Contracts\NewsProvider;
use App\Support\News\NewsArticleData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Haalt artikelen uit een configureerbare lijst RSS-feeds (config/news.php).
 * Geen API-key nodig.
 */
class RssNewsProvider implements NewsProvider
{
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

            return $this->parse($source, $response->body());
        } catch (Throwable $e) {
            Log::warning('RSS-feed ophalen faalde', ['source' => $source, 'error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return array<int, NewsArticleData>
     */
    private function parse(string $source, string $body): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            return [];
        }

        // Ondersteun zowel RSS (channel>item) als Atom (entry).
        $items = $xml->channel->item ?? $xml->item ?? $xml->entry ?? [];
        $articles = [];

        foreach ($items as $item) {
            $link = $this->extractLink($item);
            $title = trim((string) $item->title);

            if ($link === '' || $title === '') {
                continue;
            }

            $articles[] = new NewsArticleData(
                source: $source,
                url: $link,
                title: $title,
                publishedAt: $this->parseDate($item),
                description: $this->cleanText((string) ($item->description ?? $item->summary ?? '')),
                language: null,
            );
        }

        return $articles;
    }

    private function extractLink($item): string
    {
        // RSS gebruikt <link>tekst</link>; Atom gebruikt <link href="..."/>.
        $link = trim((string) $item->link);

        if ($link === '' && isset($item->link['href'])) {
            $link = trim((string) $item->link['href']);
        }

        return $link;
    }

    private function parseDate($item): Carbon
    {
        $raw = (string) ($item->pubDate ?? $item->published ?? $item->updated ?? '');

        try {
            return $raw !== '' ? Carbon::parse($raw) : Carbon::now();
        } catch (Throwable) {
            return Carbon::now();
        }
    }

    private function cleanText(string $html): ?string
    {
        $text = trim(html_entity_decode(strip_tags($html)));

        return $text === '' ? null : mb_substr($text, 0, 2000);
    }
}
