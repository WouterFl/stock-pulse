<?php

use App\Services\Quotes\Providers\FinnhubProvider;
use App\Services\Quotes\Providers\StooqProvider;
use App\Services\Quotes\Providers\YahooFinanceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Provider-prioriteit
    |--------------------------------------------------------------------------
    |
    | De QuoteFetcher probeert deze providers op volgorde tot er één een
    | succesvolle response geeft. Providers die niet geconfigureerd zijn
    | (bv. ontbrekende API-key) worden overgeslagen.
    |
    */
    'providers' => [
        YahooFinanceProvider::class,
        StooqProvider::class,
        FinnhubProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP-timeout (seconden)
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('QUOTE_FETCH_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Provider-specifieke instellingen
    |--------------------------------------------------------------------------
    */
    'finnhub' => [
        'key' => env('FINNHUB_API_KEY'),
        'base_url' => 'https://finnhub.io/api/v1',
    ],

    'yahoo' => [
        'base_url' => 'https://query1.finance.yahoo.com/v8/finance/chart',
    ],

    'stooq' => [
        'base_url' => 'https://stooq.com/q/l/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default scrape-interval (seconden)
    |--------------------------------------------------------------------------
    |
    | Per bedrijf override-baar via Company.polling_interval_seconds.
    |
    */
    'default_interval_seconds' => 60,
];
