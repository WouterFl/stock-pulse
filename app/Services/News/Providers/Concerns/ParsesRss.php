<?php

namespace App\Services\News\Providers\Concerns;

use App\Support\News\NewsArticleData;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Gedeelde RSS/Atom-parsing voor de nieuwsproviders.
 */
trait ParsesRss
{
    /**
     * @param  array<int, string>  $tickers  Optionele tickers om aan elk artikel te hangen
     *                                       (bv. bij een per-bedrijf feed → api_tagged-match).
     * @return array<int, NewsArticleData>
     */
    protected function parseRssItems(string $source, string $body, array $tickers = []): array
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
                publishedAt: $this->parseRssDate($item),
                description: $this->cleanRssText((string) ($item->description ?? $item->summary ?? '')),
                language: null,
                tickers: $tickers,
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

    private function parseRssDate($item): Carbon
    {
        $raw = (string) ($item->pubDate ?? $item->published ?? $item->updated ?? '');

        try {
            return $raw !== '' ? Carbon::parse($raw) : Carbon::now();
        } catch (Throwable) {
            return Carbon::now();
        }
    }

    private function cleanRssText(string $html): ?string
    {
        $text = trim(html_entity_decode(strip_tags($html)));

        return $text === '' ? null : mb_substr($text, 0, 2000);
    }
}
