<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Company;
use App\Models\NewsArticle;
use App\Services\Alerts\AlertNewsLinker;
use App\Services\Alerts\NewsSpikeDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AlertNewsTest extends TestCase
{
    use RefreshDatabase;

    private function articleFor(Company $c, Carbon $publishedAt): NewsArticle
    {
        $article = NewsArticle::create([
            'source' => 'rss',
            'url' => 'https://x.test/'.uniqid(),
            'title' => 'News',
            'published_at' => $publishedAt,
        ]);
        $c->newsArticles()->attach($article->id, ['match_type' => 'ticker']);

        return $article;
    }

    public function test_linker_attaches_articles_within_window(): void
    {
        config(['alerts.default_window_minutes' => 60]);
        $c = Company::factory()->create();
        $triggeredAt = Carbon::now();

        $inWindow = $this->articleFor($c, $triggeredAt->copy()->subMinutes(30));
        $tooOld = $this->articleFor($c, $triggeredAt->copy()->subMinutes(120));
        $afterBell = $this->articleFor($c, $triggeredAt->copy()->addMinutes(10)); // binnen +15min

        $alert = Alert::create([
            'company_id' => $c->id,
            'type' => 'absolute_threshold',
            'severity' => 'warning',
            'title' => 'test',
            'details' => ['window_minutes' => 60, 'change_percent' => 4.0],
            'triggered_at' => $triggeredAt,
        ]);

        $linked = (new AlertNewsLinker)->link($alert);

        $this->assertSame(2, $linked);
        $ids = $alert->newsArticles()->pluck('news_articles.id')->all();
        $this->assertContains($inWindow->id, $ids);
        $this->assertContains($afterBell->id, $ids);
        $this->assertNotContains($tooOld->id, $ids);
    }

    public function test_news_spike_creates_alert_above_threshold(): void
    {
        config(['news.spike_threshold' => 3]);
        $c = Company::factory()->create();

        for ($i = 0; $i < 4; $i++) {
            $this->articleFor($c, Carbon::now()->subMinutes(10 * $i));
        }

        $alerts = (new NewsSpikeDetector)->run();

        $this->assertCount(1, $alerts);
        $this->assertSame('news_spike', $alerts->first()->type);
        $this->assertSame(4, $alerts->first()->details['article_count']);
    }

    public function test_news_spike_respects_threshold_and_cooldown(): void
    {
        config(['news.spike_threshold' => 5]);
        $c = Company::factory()->create();
        $this->articleFor($c, Carbon::now()->subMinutes(5)); // slechts 1 < 5

        $this->assertCount(0, (new NewsSpikeDetector)->run());

        // Genoeg artikelen → 1 alert, maar tweede run binnen cooldown → 0.
        for ($i = 0; $i < 6; $i++) {
            $this->articleFor($c, Carbon::now()->subMinutes($i));
        }
        $this->assertCount(1, (new NewsSpikeDetector)->run());
        $this->assertCount(0, (new NewsSpikeDetector)->run());
    }
}
