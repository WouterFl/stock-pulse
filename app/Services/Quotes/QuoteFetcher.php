<?php

namespace App\Services\Quotes;

use App\Contracts\QuoteProvider;
use App\Models\Company;
use App\Support\Quotes\QuoteData;
use Illuminate\Support\Facades\Log;

/**
 * Probeert de geregistreerde QuoteProviders op prioriteitsvolgorde tot er één
 * een bruikbare koers teruggeeft. Niet-geconfigureerde providers worden
 * overgeslagen; failover wordt gelogd.
 */
class QuoteFetcher
{
    /**
     * @param  iterable<QuoteProvider>  $providers
     */
    public function __construct(private iterable $providers) {}

    public function fetch(Company $company): ?QuoteData
    {
        $attempted = [];

        foreach ($this->providers as $provider) {
            if (! $provider->isConfigured()) {
                continue;
            }

            $attempted[] = $provider->name();
            $quote = $provider->fetch($company);

            if ($quote !== null) {
                if (count($attempted) > 1) {
                    Log::info('Quote opgehaald na failover', [
                        'company' => $company->ticker,
                        'used' => $provider->name(),
                        'tried' => $attempted,
                    ]);
                }

                return $quote;
            }
        }

        Log::warning('Geen enkele quote-provider gaf data', [
            'company' => $company->ticker,
            'tried' => $attempted,
        ]);

        return null;
    }
}
