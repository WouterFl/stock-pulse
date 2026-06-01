<?php

namespace Tests\Feature;

use App\Contracts\NewsProvider;
use App\Models\Company;
use App\Models\NewsArticle;
use App\Services\News\CompanyArticleMatcher;
use App\Services\News\NewsIngester;
use App\Services\News\Providers\RssNewsProvider;
use App\Support\News\NewsArticleData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NewsIngesterTest extends TestCase
{
    use RefreshDatabase;

    private function fakeProvider(array $articles): NewsProvider
    {
        return new class($articles) implements NewsProvider
        {
            public function __construct(private array $articles) {}

            public function name(): string
            {
                return 'fake';
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function fetch(): array
            {
                return $this->articles;
            }
        };
    }

    private function ingester(NewsProvider $provider): NewsIngester
    {
        return new NewsIngester([$provider], new CompanyArticleMatcher);
    }

    public function test_it_dedups_within_batch_and_matches_companies(): void
    {
        Company::factory()->create(['ticker' => 'AAPL', 'exchange' => 'NASDAQ', 'name' => 'Apple Inc.']);

        $provider = $this->fakeProvider([
            new NewsArticleData('fake', 'https://x.test/a', 'AAPL surges 6%', Carbon::now(), tickers: ['AAPL']),
            new NewsArticleData('fake', 'https://x.test/a', 'AAPL surges 6% (dupe)', Carbon::now()), // zelfde url
            new NewsArticleData('fake', 'https://x.test/b', 'Unrelated market news', Carbon::now()),
        ]);

        $stats = $this->ingester($provider)->run();

        $this->assertSame(2, $stats['fetched']); // dedup binnen batch
        $this->assertSame(2, $stats['created']);
        $this->assertSame(1, $stats['linked']);  // alleen AAPL-artikel gekoppeld

        $article = NewsArticle::where('url', 'https://x.test/a')->first();
        $this->assertSame('api_tagged', $article->companies()->first()->pivot->match_type);
    }

    public function test_running_twice_does_not_create_duplicates(): void
    {
        $provider = $this->fakeProvider([
            new NewsArticleData('fake', 'https://x.test/a', 'Some headline', Carbon::now()),
        ]);

        $this->ingester($provider)->run();
        $second = $this->ingester($provider)->run();

        $this->assertSame(0, $second['created']);
        $this->assertSame(1, NewsArticle::count());
    }

    public function test_rss_provider_parses_feed_items(): void
    {
        config(['news.feeds' => [['source' => 'test_rss', 'url' => 'https://feed.test/rss']]]);

        Http::fake([
            'feed.test/*' => Http::response(
                '<?xml version="1.0"?><rss><channel>'.
                '<item><title>Apple news</title><link>https://news.test/1</link>'.
                '<description><![CDATA[<p>Some &amp; body</p>]]></description>'.
                '<pubDate>Mon, 01 Jun 2026 10:00:00 GMT</pubDate></item>'.
                '</channel></rss>'
            ),
        ]);

        $articles = (new RssNewsProvider)->fetch();

        $this->assertCount(1, $articles);
        $this->assertSame('Apple news', $articles[0]->title);
        $this->assertSame('https://news.test/1', $articles[0]->url);
        $this->assertSame('Some & body', $articles[0]->description);
    }
}
