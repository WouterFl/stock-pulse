<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\News\NewsIngester;
use App\Services\News\Providers\FinnhubCompanyNewsProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class BackfillNewsCommand extends Command
{
    protected $signature = 'news:backfill
        {--days=90 : Hoeveel dagen terug ophalen}
        {--ticker= : Beperk tot één ticker (anders alle actieve bedrijven)}';

    protected $description = 'Laad historisch bedrijfsnieuws in via Finnhub company-news (vereist FINNHUB_API_KEY)';

    public function handle(FinnhubCompanyNewsProvider $finnhub, NewsIngester $ingester): int
    {
        if (! $finnhub->isConfigured()) {
            $this->error('FINNHUB_API_KEY ontbreekt. Zet een (gratis) key in .env en probeer opnieuw.');

            return self::FAILURE;
        }

        $days = max(1, (int) $this->option('days'));
        $from = Carbon::now()->subDays($days);
        $to = Carbon::now();

        $companies = Company::query()
            ->active()
            ->when($this->option('ticker'), fn ($q) => $q->where('ticker', strtoupper($this->option('ticker'))))
            ->get();

        if ($companies->isEmpty()) {
            $this->warn('Geen (matchende) actieve bedrijven gevonden.');

            return self::SUCCESS;
        }

        $this->info("Historie ophalen ({$days} dagen) voor {$companies->count()} bedrijf(en)...");

        $all = [];
        foreach ($companies as $company) {
            $articles = $finnhub->fetch($company, $from, $to);
            $this->line("  {$company->ticker}: ".count($articles).' artikelen');
            $all = array_merge($all, $articles);

            // Sober blijven t.o.v. de Finnhub rate limit (60/min op de free tier).
            if (! app()->runningUnitTests()) {
                usleep(1_100_000);
            }
        }

        $stats = $ingester->ingest($all);

        $this->newLine();
        $this->table(
            ['Opgehaald (uniek)', 'Nieuw opgeslagen', 'Gekoppeld'],
            [[$stats['fetched'], $stats['created'], $stats['linked']]],
        );

        return self::SUCCESS;
    }
}
