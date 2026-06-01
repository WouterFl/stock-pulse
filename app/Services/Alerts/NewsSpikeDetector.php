<?php

namespace App\Services\Alerts;

use App\Models\Alert;
use App\Models\Company;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Detecteert een "news_spike": ongebruikelijk veel nieuwe artikelen voor één
 * bedrijf binnen een uur — vaak een leading indicator, ook als de koers (nog)
 * niet beweegt.
 */
class NewsSpikeDetector
{
    /**
     * @return Collection<int, Alert>
     */
    public function run(): Collection
    {
        $threshold = (int) config('news.spike_threshold', 5);
        $since = Carbon::now()->subHour();
        $created = collect();

        Company::query()->active()->each(function (Company $company) use ($threshold, $since, $created) {
            $count = $company->newsArticles()
                ->where('published_at', '>=', $since)
                ->count();

            if ($count < $threshold) {
                return;
            }

            // Cooldown: geen tweede spike-alert binnen een uur.
            $cooldown = Alert::query()
                ->where('company_id', $company->id)
                ->where('type', 'news_spike')
                ->where('triggered_at', '>=', $since)
                ->exists();

            if ($cooldown) {
                return;
            }

            $alert = Alert::create([
                'company_id' => $company->id,
                'type' => 'news_spike',
                'severity' => 'info',
                'title' => "{$company->ticker}: {$count} nieuwsartikelen in 1 uur",
                'details' => ['article_count' => $count, 'window_minutes' => 60],
                'triggered_at' => Carbon::now(),
            ]);

            // Koppel de betreffende artikelen.
            $articleIds = $company->newsArticles()
                ->where('published_at', '>=', $since)
                ->pluck('news_articles.id')
                ->all();
            $alert->newsArticles()->syncWithoutDetaching($articleIds);

            $created->push($alert);
        });

        return $created;
    }
}
