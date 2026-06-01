<?php

namespace App\Console\Commands;

use App\Jobs\FetchQuoteJob;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DispatchQuoteFetchesCommand extends Command
{
    protected $signature = 'quotes:dispatch';

    protected $description = 'Dispatch FetchQuoteJob voor elk actief bedrijf dat aan zijn polling-interval toe is';

    public function handle(): int
    {
        $now = Carbon::now();
        $dispatched = 0;

        Company::query()
            ->active()
            ->with('latestQuote')
            ->each(function (Company $company) use ($now, &$dispatched) {
                if ($this->isDue($company, $now)) {
                    FetchQuoteJob::dispatch($company);
                    $dispatched++;
                }
            });

        $this->info("Dispatched {$dispatched} quote fetch job(s).");

        return self::SUCCESS;
    }

    /**
     * Een bedrijf is "due" als er nog geen koers is, of de laatste koers ouder
     * is dan zijn polling-interval. Zo wordt een bedrijf met 300s niet elke
     * minuut opnieuw opgehaald.
     */
    private function isDue(Company $company, Carbon $now): bool
    {
        $last = $company->latestQuote;

        if ($last === null) {
            return true;
        }

        $interval = $company->polling_interval_seconds ?: config('quotes.default_interval_seconds', 60);

        // Kleine marge (5s) zodat een interval van 60s niet net één tick mist.
        return $last->fetched_at->addSeconds($interval)->subSeconds(5)->lte($now);
    }
}
