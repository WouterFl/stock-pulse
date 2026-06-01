<?php

namespace App\Contracts;

use App\Models\Company;
use App\Support\Quotes\QuoteData;

interface QuoteProvider
{
    /**
     * Unieke, leesbare naam van de provider (gebruikt als `source` + in logs).
     */
    public function name(): string;

    /**
     * Is deze provider bruikbaar? (bv. API-key aanwezig)
     */
    public function isConfigured(): bool;

    /**
     * Haal de actuele koers op voor het bedrijf, of geef null bij falen.
     */
    public function fetch(Company $company): ?QuoteData;
}
