<?php

namespace App\Jobs;

use App\Services\Alerts\NewsSpikeDetector;
use App\Services\News\NewsIngester;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Draait de NewsIngester op de aparte `news`-queue, zodat trage RSS-feeds de
 * koers-pipeline (queue `quotes`) niet ophouden.
 */
class FetchNewsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 600;

    public function __construct()
    {
        $this->onQueue('news');
    }

    public function handle(NewsIngester $ingester, NewsSpikeDetector $spikeDetector): void
    {
        $ingester->run();

        // Na ingest: detecteer nieuws-spikes (SP-21).
        $spikeDetector->run();
    }

    public function uniqueId(): string
    {
        return 'fetch-news';
    }
}
