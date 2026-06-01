<?php

namespace App\Console\Commands;

use App\Services\Alerts\NewsSpikeDetector;
use App\Services\News\NewsIngester;
use Illuminate\Console\Command;

class FetchNewsCommand extends Command
{
    protected $signature = 'news:fetch';

    protected $description = 'Haal direct nieuws op uit alle providers, koppel aan bedrijven en detecteer spikes (synchroon)';

    public function handle(NewsIngester $ingester, NewsSpikeDetector $spikeDetector): int
    {
        $this->info('Nieuws ophalen uit alle providers...');

        $stats = $ingester->run();
        $spikes = $spikeDetector->run();

        $this->table(
            ['Opgehaald', 'Nieuw', 'Gekoppeld', 'Spike-alerts'],
            [[$stats['fetched'], $stats['created'], $stats['linked'], $spikes->count()]],
        );

        return self::SUCCESS;
    }
}
