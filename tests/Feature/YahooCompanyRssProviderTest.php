<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Services\News\Providers\YahooCompanyRssProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YahooCompanyRssProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fetches_per_company_and_tags_with_ticker(): void
    {
        Company::factory()->create(['ticker' => 'AAPL', 'exchange' => 'NASDAQ']);
        Company::factory()->create(['ticker' => 'SHELL', 'exchange' => 'AMS']);

        Http::fake([
            'feeds.finance.yahoo.com/*' => Http::response(
                '<?xml version="1.0"?><rss><channel>'.
                '<item><title>Company headline</title><link>https://news.test/'.uniqid().'</link>'.
                '<pubDate>Mon, 01 Jun 2026 10:00:00 GMT</pubDate></item>'.
                '</channel></rss>'
            ),
        ]);

        $articles = (new YahooCompanyRssProvider)->fetch();

        // Eén item per actief bedrijf, elk getagd met zijn eigen ticker.
        $this->assertCount(2, $articles);
        $tickers = collect($articles)->pluck('tickers')->flatten()->all();
        $this->assertContains('AAPL', $tickers);
        $this->assertContains('SHELL', $tickers);
    }

    public function test_amsterdam_ticker_uses_as_suffix(): void
    {
        Company::factory()->create(['ticker' => 'ASML', 'exchange' => 'AMS']);

        Http::fake(function ($request) {
            // Het Amsterdamse symbool moet .AS-suffix krijgen.
            $this->assertStringContainsString('s=ASML.AS', urldecode($request->url()));

            return Http::response('<?xml version="1.0"?><rss><channel></channel></rss>');
        });

        (new YahooCompanyRssProvider)->fetch();
    }
}
