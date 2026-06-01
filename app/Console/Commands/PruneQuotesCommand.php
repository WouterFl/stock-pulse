<?php

namespace App\Console\Commands;

use App\Models\Quote;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PruneQuotesCommand extends Command
{
    protected $signature = 'quotes:prune {--days=30 : Verwijder quotes ouder dan dit aantal dagen}';

    protected $description = 'Verwijder historische quotes ouder dan X dagen (in chunks)';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->error('--days moet minimaal 1 zijn.');

            return self::FAILURE;
        }

        $cutoff = Carbon::now()->subDays($days);
        $deleted = 0;
        $chunk = 1000;

        // Per chunk ids ophalen en verwijderen — werkt op sqlite én mysql
        // (DELETE ... LIMIT wordt niet door alle drivers ondersteund).
        do {
            $ids = Quote::query()
                ->where('fetched_at', '<', $cutoff)
                ->limit($chunk)
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                Quote::whereIn('id', $ids)->delete();
                $deleted += $ids->count();
            }
        } while ($ids->count() === $chunk);

        $this->info("Verwijderd: {$deleted} quote(s) ouder dan {$days} dagen (vóór {$cutoff->toDateTimeString()}).");

        return self::SUCCESS;
    }
}
