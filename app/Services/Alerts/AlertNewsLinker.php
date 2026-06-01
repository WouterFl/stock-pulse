<?php

namespace App\Services\Alerts;

use App\Models\Alert;

/**
 * Koppelt relevante nieuwsartikelen aan een alert: artikelen van het bedrijf
 * gepubliceerd in [triggered_at - window_minutes, triggered_at + 15 min].
 * Dat venster dekt zowel de aanleiding als het "door-de-bel"-effect.
 */
class AlertNewsLinker
{
    public function link(Alert $alert): int
    {
        $windowMinutes = (int) ($alert->details['window_minutes'] ?? config('alerts.default_window_minutes', 60));

        $from = $alert->triggered_at->copy()->subMinutes($windowMinutes);
        $until = $alert->triggered_at->copy()->addMinutes(15);

        $articleIds = $alert->company
            ->newsArticles()
            ->whereBetween('published_at', [$from, $until])
            ->pluck('news_articles.id')
            ->all();

        if ($articleIds === []) {
            return 0;
        }

        $alert->newsArticles()->syncWithoutDetaching($articleIds);

        return count($articleIds);
    }
}
