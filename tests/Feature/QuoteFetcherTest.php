<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Services\Quotes\QuoteFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QuoteFetcherTest extends TestCase
{
    use RefreshDatabase;

    private function company(): Company
    {
        return Company::create([
            'ticker' => 'AAPL',
            'exchange' => 'NASDAQ',
            'name' => 'Apple Inc.',
            'currency' => 'USD',
        ]);
    }

    public function test_yahoo_provider_returns_quote_with_derived_change_percent(): void
    {
        Http::fake([
            'query1.finance.yahoo.com/*' => Http::response([
                'chart' => ['result' => [[
                    'meta' => [
                        'regularMarketPrice' => 110.0,
                        'previousClose' => 100.0,
                        'regularMarketDayHigh' => 112.0,
                        'regularMarketDayLow' => 99.0,
                        'regularMarketVolume' => 12345,
                    ],
                ]]],
            ]),
        ]);

        $quote = app(QuoteFetcher::class)->fetch($this->company());

        $this->assertNotNull($quote);
        $this->assertSame('yahoo', $quote->source);
        $this->assertSame(110.0, $quote->price);
        // (110-100)/100*100 = 10%
        $this->assertSame(10.0, $quote->changePercent);
    }

    public function test_falls_over_to_stooq_when_yahoo_fails(): void
    {
        Http::fake([
            'query1.finance.yahoo.com/*' => Http::response('error', 500),
            'stooq.com/*' => Http::response(
                "Symbol,Date,Time,Open,High,Low,Close,Volume\n".
                "AAPL.US,2026-05-31,22:00:00,100,105,98,104.5,5000\n"
            ),
        ]);

        $quote = app(QuoteFetcher::class)->fetch($this->company());

        $this->assertNotNull($quote);
        $this->assertSame('stooq', $quote->source);
        $this->assertSame(104.5, $quote->price);
        $this->assertSame(5000, $quote->volume);
    }

    public function test_returns_null_when_all_providers_fail(): void
    {
        Http::fake([
            'query1.finance.yahoo.com/*' => Http::response('error', 500),
            'stooq.com/*' => Http::response("Symbol,Date,Time,Open,High,Low,Close,Volume\nAAPL.US,N/D,N/D,N/D,N/D,N/D,N/D,N/D\n"),
            'finnhub.io/*' => Http::response(['c' => 0]),
        ]);

        $quote = app(QuoteFetcher::class)->fetch($this->company());

        $this->assertNull($quote);
    }
}
