<?php

namespace App\Services\News\Providers;

use App\Contracts\NewsProvider;
use App\Models\Company;
use App\Services\News\Providers\Concerns\ParsesRss;
use App\Support\News\NewsArticleData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Per-bedrijf nieuws via Yahoo Finance's ticker-RSS-feed:
 *   https://feeds.finance.yahoo.com/rss/2.0/headline?s={symbol}
 *
 * Veel gerichter dan de algemene feeds: elk artikel wordt met de ticker getagd,
 * zodat het gegarandeerd aan het juiste bedrijf wordt gekoppeld (api_tagged).
 * Geen API-key nodig.
 */
class YahooCompanyRssProvider implements NewsProvider
{
    use ParsesRss;

    /**
     * Beurs → Yahoo-suffix (gelijk aan de koers-provider).
     */
    private const EXCHANGE_SUFFIX = [
        'NASDAQ' => '',
        'NYSE' => '',
        'AMS' => '.AS',
        'LSE' => '.L',
        'FRA' => '.F',
        'ETR' => '.DE',
    ];

    public function name(): string
    {
        return 'yahoo_company_rss';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function fetch(): array
    {
        $articles = [];

        Company::query()->active()->get()->each(function (Company $company) use (&$articles) {
            $articles = array_merge($articles, $this->fetchForCompany($company));
        });

        return $articles;
    }

    /**
     * @return array<int, NewsArticleData>
     */
    private function fetchForCompany(Company $company): array
    {
        $symbol = $company->ticker.(self::EXCHANGE_SUFFIX[$company->exchange] ?? '');

        try {
            $response = Http::timeout(config('news.timeout', 10))
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (StockPulse)'])
                ->get('https://feeds.finance.yahoo.com/rss/2.0/headline', [
                    's' => $symbol,
                    'region' => 'US',
                    'lang' => 'en-US',
                ]);

            if (! $response->successful()) {
                return [];
            }

            // Tag elk artikel met de ticker → de matcher koppelt via api_tagged.
            return $this->parseRssItems($this->name(), $response->body(), [$company->ticker]);
        } catch (Throwable $e) {
            Log::warning('Yahoo company-RSS faalde', [
                'company' => $company->ticker,
                'symbol' => $symbol,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
