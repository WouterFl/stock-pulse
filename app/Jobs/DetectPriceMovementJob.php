<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\Quote;
use App\Services\Alerts\AlertNewsLinker;
use App\Services\Alerts\PriceMovementDetector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Detecteert koersbewegingen na elke FetchQuoteJob (SP-20).
 * Maakt Alert-records aan via de PriceMovementDetector.
 */
class DetectPriceMovementJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $companyId, public int $quoteId)
    {
        $this->onQueue('quotes');
    }

    public function handle(PriceMovementDetector $detector, AlertNewsLinker $linker): void
    {
        $company = Company::find($this->companyId);
        $quote = Quote::find($this->quoteId);

        if ($company === null || $quote === null) {
            return;
        }

        $alerts = $detector->detect($company, $quote);

        // Koppel relevante nieuwsartikelen aan elke nieuwe alert (SP-21).
        foreach ($alerts as $alert) {
            $linker->link($alert);
        }
    }
}
