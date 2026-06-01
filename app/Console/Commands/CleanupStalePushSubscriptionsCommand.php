<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use NotificationChannels\WebPush\PushSubscription;

class CleanupStalePushSubscriptionsCommand extends Command
{
    protected $signature = 'push:cleanup-stale {--days=90 : Verwijder subscriptions zonder activiteit sinds dit aantal dagen}';

    protected $description = 'Verwijder push-subscriptions die al lange tijd geen succesvolle delivery hadden';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = Carbon::now()->subDays($days);

        // updated_at wordt bij een succesvolle delivery aangeraakt (NotificationSent-listener).
        $deleted = PushSubscription::query()
            ->where('updated_at', '<', $cutoff)
            ->delete();

        $this->info("Verwijderd: {$deleted} verouderde push-subscription(s) (ouder dan {$days} dagen).");

        return self::SUCCESS;
    }
}
