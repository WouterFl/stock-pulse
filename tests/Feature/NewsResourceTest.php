<?php

namespace Tests\Feature;

use App\Filament\Resources\NewsArticles\NewsArticleResource;
use App\Filament\Resources\NewsArticles\Pages\ListNewsArticles;
use App\Models\Company;
use App\Models\NewsArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class NewsResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_news_list_shows_articles(): void
    {
        $this->actingAs(User::factory()->create());

        $company = Company::factory()->create(['ticker' => 'AAPL']);
        $article = NewsArticle::create([
            'source' => 'rss',
            'url' => 'https://x.test/news',
            'title' => 'Apple beats earnings',
            'published_at' => Carbon::now(),
        ]);
        $article->companies()->attach($company->id, ['match_type' => 'ticker']);

        Livewire::test(ListNewsArticles::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$article]);
    }

    public function test_news_resource_is_read_only(): void
    {
        $this->assertFalse(NewsArticleResource::canCreate());
        $article = NewsArticle::create([
            'source' => 'rss',
            'url' => 'https://x.test/n2',
            'title' => 'Headline',
            'published_at' => Carbon::now(),
        ]);
        $this->assertFalse(NewsArticleResource::canEdit($article));
        $this->assertFalse(NewsArticleResource::canDelete($article));
    }
}
