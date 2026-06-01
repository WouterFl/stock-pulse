<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Een handvol echte, liquide tickers zodat de scraping-pipeline (Sprint 2)
     * meteen zinvolle data oplevert. Idempotent via updateOrCreate.
     */
    public function run(): void
    {
        $companies = [
            ['ticker' => 'AAPL', 'exchange' => 'NASDAQ', 'name' => 'Apple Inc.', 'currency' => 'USD', 'sector' => 'Technology', 'industry' => 'Consumer Electronics'],
            ['ticker' => 'MSFT', 'exchange' => 'NASDAQ', 'name' => 'Microsoft Corporation', 'currency' => 'USD', 'sector' => 'Technology', 'industry' => 'Software'],
            ['ticker' => 'NVDA', 'exchange' => 'NASDAQ', 'name' => 'NVIDIA Corporation', 'currency' => 'USD', 'sector' => 'Technology', 'industry' => 'Semiconductors'],
            ['ticker' => 'ASML', 'exchange' => 'AMS', 'name' => 'ASML Holding N.V.', 'currency' => 'EUR', 'sector' => 'Technology', 'industry' => 'Semiconductors'],
            ['ticker' => 'SHELL', 'exchange' => 'AMS', 'name' => 'Shell plc', 'currency' => 'EUR', 'sector' => 'Energy', 'industry' => 'Oil & Gas'],
        ];

        foreach ($companies as $data) {
            Company::updateOrCreate(
                ['ticker' => $data['ticker'], 'exchange' => $data['exchange']],
                $data,
            );
        }
    }
}
