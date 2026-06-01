<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\NewsArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NewsBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_requires_finnhub_key(): void
    {
        config(['news.finnhub.key' => null]);

        $this->artisan('news:backfill')
            ->expectsOutputToContain('FINNHUB_API_KEY ontbreekt')
            ->assertFailed();
    }

    public function test_backfill_imports_and_links_historic_company_news(): void
    {
        config(['news.finnhub.key' => 'test-key']);
        $company = Company::factory()->create(['ticker' => 'AAPL', 'exchange' => 'NASDAQ']);

        Http::fake([
            'finnhub.io/*' => Http::response([
                [
                    'id' => 1,
                    'headline' => 'Apple announces record quarter',
                    'summary' => 'Strong results.',
                    'url' => 'https://news.test/aapl-1',
                    'image' => 'https://img.test/1.jpg',
                    'datetime' => Carbon::now()->subDays(40)->timestamp,
                ],
                [
                    'id' => 2,
                    'headline' => 'Apple unveils new product',
                    'summary' => null,
                    'url' => 'https://news.test/aapl-2',
                    'datetime' => Carbon::now()->subDays(20)->timestamp,
                ],
            ]),
        ]);

        $this->artisan('news:backfill', ['--days' => 90, '--ticker' => 'aapl'])
            ->assertSuccessful();

        $this->assertDatabaseHas('news_articles', ['url' => 'https://news.test/aapl-1', 'source' => 'finnhub']);
        $this->assertSame(2, $company->newsArticles()->count());
        // Datum uit de Finnhub-timestamp (ouder dan RSS terug zou gaan).
        $this->assertTrue(
            NewsArticle::where('url', 'https://news.test/aapl-1')->first()->published_at->lt(Carbon::now()->subDays(30))
        );
    }
}
