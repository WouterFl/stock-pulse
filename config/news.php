<?php

use App\Services\News\Providers\MarketauxProvider;
use App\Services\News\Providers\NewsApiProvider;
use App\Services\News\Providers\RssNewsProvider;
use App\Services\News\Providers\YahooCompanyRssProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Actieve providers
    |--------------------------------------------------------------------------
    |
    | De NewsIngester haalt uit elke geconfigureerde provider. Niet-geconfigureerde
    | providers (ontbrekende API-key) worden automatisch overgeslagen.
    |
    */
    'providers' => [
        YahooCompanyRssProvider::class, // gericht per ticker → vult de bedrijfs-nieuwstabs
        RssNewsProvider::class,         // brede markt-feeds
        MarketauxProvider::class,
        NewsApiProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | RSS-feeds
    |--------------------------------------------------------------------------
    |
    | Gratis financiële feeds. `source` wordt opgeslagen op het artikel.
    |
    */
    'feeds' => [
        ['source' => 'yahoo_finance_rss', 'url' => 'https://finance.yahoo.com/news/rssindex'],
        ['source' => 'cnbc_rss', 'url' => 'https://search.cnbc.com/rs/search/combinedcms/view.xml?partnerId=wrss01&id=100003114'],
        ['source' => 'nu_beurs_rss', 'url' => 'https://www.nu.nl/rss/Economie'],
    ],

    /*
    |--------------------------------------------------------------------------
    | API-providers
    |--------------------------------------------------------------------------
    */
    'marketaux' => [
        'key' => env('MARKETAUX_API_KEY'),
        'base_url' => 'https://api.marketaux.com/v1',
        'language' => 'en',
    ],

    'newsapi' => [
        'key' => env('NEWSAPI_API_KEY'),
        'base_url' => 'https://newsapi.org/v2',
    ],

    // Finnhub company-news (historische backfill via `php artisan news:backfill`).
    // Deelt de FINNHUB_API_KEY met de koers-provider.
    'finnhub' => [
        'key' => env('FINNHUB_API_KEY'),
        'base_url' => 'https://finnhub.io/api/v1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Fetch-instellingen
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('QUOTE_FETCH_TIMEOUT', 10),
    'interval_minutes' => (int) env('NEWS_FETCH_INTERVAL_MINUTES', 5),

    /*
    | News-spike-detectie (SP-21): aantal nieuwe artikelen binnen een uur dat
    | een spike-alert triggert.
    */
    'spike_threshold' => 5,
];
