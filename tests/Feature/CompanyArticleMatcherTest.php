<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Services\News\CompanyArticleMatcher;
use App\Support\News\NewsArticleData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CompanyArticleMatcherTest extends TestCase
{
    use RefreshDatabase;

    private function article(string $title, string $desc = '', array $tickers = []): NewsArticleData
    {
        return new NewsArticleData(
            source: 'test',
            url: 'https://x.test/'.md5($title),
            title: $title,
            publishedAt: Carbon::now(),
            description: $desc,
            tickers: $tickers,
        );
    }

    /** @return Collection<int, Company> */
    private function companies(): Collection
    {
        return collect([
            Company::factory()->create(['ticker' => 'AAPL', 'exchange' => 'NASDAQ', 'name' => 'Apple Inc.']),
            Company::factory()->create(['ticker' => 'SHELL', 'exchange' => 'AMS', 'name' => 'Shell plc']),
        ]);
    }

    public function test_api_tagged_match_wins(): void
    {
        $matcher = new CompanyArticleMatcher;
        $result = $matcher->match($this->article('Markets rally today', '', ['AAPL']), $this->companies());

        $apple = Company::where('ticker', 'AAPL')->first();
        $this->assertSame('api_tagged', $result[$apple->id]);
    }

    public function test_ticker_match_is_case_sensitive_whole_word(): void
    {
        $matcher = new CompanyArticleMatcher;
        $apple = Company::factory()->create(['ticker' => 'AAPL', 'exchange' => 'NASDAQ', 'name' => 'Apple Inc.']);
        $companies = collect([$apple]);

        $this->assertArrayHasKey($apple->id, $matcher->match($this->article('AAPL jumps 5%'), $companies));
        // Lowercase mag niet matchen als ticker (zou ruis geven).
        $this->assertArrayNotHasKey($apple->id, $matcher->match($this->article('the aapl word'), $companies));
    }

    public function test_name_match_ignores_legal_suffix(): void
    {
        $matcher = new CompanyArticleMatcher;
        $apple = Company::factory()->create(['ticker' => 'AAPL', 'exchange' => 'NASDAQ', 'name' => 'Apple Inc.']);
        $companies = collect([$apple]);

        $result = $matcher->match($this->article('Apple unveils new product line'), $companies);
        $this->assertSame('name', $result[$apple->id]);
    }

    public function test_article_can_match_multiple_companies(): void
    {
        $matcher = new CompanyArticleMatcher;
        $result = $matcher->match(
            $this->article('Apple and Shell report earnings', '', []),
            $this->companies(),
        );

        $this->assertCount(2, $result);
    }

    public function test_no_match_returns_empty(): void
    {
        $matcher = new CompanyArticleMatcher;
        $result = $matcher->match($this->article('Generic market news with no companies'), $this->companies());

        $this->assertEmpty($result);
    }
}
