<?php

namespace App\Jobs;

use App\Events\QuoteUpdated;
use App\Models\Company;
use App\Models\Quote;
use App\Services\Quotes\QuoteFetcher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FetchQuoteJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Uniciteit vervalt na 5 minuten als de job zou blijven hangen.
     */
    public int $uniqueFor = 300;

    /**
     * Aantal pogingen + exponentiële backoff (10s, 30s, 60s).
     */
    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(public Company $company)
    {
        $this->onQueue('quotes');
    }

    public function handle(QuoteFetcher $fetcher): void
    {
        $data = $fetcher->fetch($this->company);

        if ($data === null) {
            Log::warning('FetchQuoteJob: geen koersdata', ['company' => $this->company->ticker]);

            return;
        }

        /** @var Quote $quote */
        $quote = $this->company->quotes()->create($data->toQuoteAttributes());

        // Bonus (SP-22): broadcast de nieuwe koers naar de company-channel.
        QuoteUpdated::dispatch($quote);

        // Na elke fetch: detecteer koersbewegingen (Sprint 4 / SP-20).
        DetectPriceMovementJob::dispatch($this->company->id, $quote->id);
    }

    /**
     * Voorkom dat dubbele jobs voor hetzelfde bedrijf tegelijk in de wachtrij staan.
     */
    public function uniqueId(): string
    {
        return 'fetch-quote-'.$this->company->id;
    }
}
