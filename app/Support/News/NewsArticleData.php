<?php

namespace App\Support\News;

use Illuminate\Support\Carbon;

/**
 * Provider-onafhankelijke representatie van één nieuwsartikel.
 */
final class NewsArticleData
{
    /**
     * @param  array<int, string>  $tickers  Door de bron meegegeven tickers (bv. Marketaux).
     */
    public function __construct(
        public string $source,
        public string $url,
        public string $title,
        public Carbon $publishedAt,
        public ?string $description = null,
        public ?string $imageUrl = null,
        public ?string $language = null,
        public ?string $externalId = null,
        public array $tickers = [],
    ) {}

    public function urlHash(): string
    {
        return hash('sha256', $this->url);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArticleAttributes(): array
    {
        return [
            'source' => $this->source,
            'external_id' => $this->externalId,
            'url' => $this->url,
            'title' => mb_substr($this->title, 0, 255),
            'description' => $this->description,
            'image_url' => $this->imageUrl,
            'published_at' => $this->publishedAt,
            'language' => $this->language,
        ];
    }
}
