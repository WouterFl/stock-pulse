<?php

namespace App\Support\Quotes;

use Illuminate\Support\Carbon;

/**
 * Provider-onafhankelijke representatie van één koers-snapshot.
 *
 * Drivers vertalen hun ruwe API-response naar dit DTO, zodat de rest van de
 * applicatie niet weet van welke bron de data komt.
 */
final readonly class QuoteData
{
    public function __construct(
        public float $price,
        public string $source,
        public Carbon $fetchedAt,
        public ?float $open = null,
        public ?float $high = null,
        public ?float $low = null,
        public ?float $previousClose = null,
        public ?int $volume = null,
        public ?float $changePercent = null,
    ) {}

    /**
     * Bereken change_percent uit previous_close indien niet expliciet geleverd.
     */
    public function withDerivedChangePercent(): self
    {
        if ($this->changePercent !== null || $this->previousClose === null || $this->previousClose == 0.0) {
            return $this;
        }

        $change = round((($this->price - $this->previousClose) / $this->previousClose) * 100, 4);

        return new self(
            price: $this->price,
            source: $this->source,
            fetchedAt: $this->fetchedAt,
            open: $this->open,
            high: $this->high,
            low: $this->low,
            previousClose: $this->previousClose,
            volume: $this->volume,
            changePercent: $change,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toQuoteAttributes(): array
    {
        return [
            'price' => $this->price,
            'open' => $this->open,
            'high' => $this->high,
            'low' => $this->low,
            'previous_close' => $this->previousClose,
            'volume' => $this->volume,
            'change_percent' => $this->changePercent,
            'source' => $this->source,
            'fetched_at' => $this->fetchedAt,
        ];
    }
}
