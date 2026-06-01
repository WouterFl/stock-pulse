<?php

namespace App\Listeners;

use NotificationChannels\WebPush\Events\NotificationFailed;

/**
 * Verwijdert een push-subscription zodra de push-service aangeeft dat hij niet
 * meer geldig is (HTTP 410 Gone / 404 Not Found). Voorkomt eindeloos
 * retryende push-jobs (SP-32).
 *
 * Let op: de webpush-package verwijdert verlopen subscriptions zelf al; deze
 * listener maakt dat gedrag expliciet/testbaar en dekt edge cases af.
 */
class CleanupExpiredSubscription
{
    public function handle(NotificationFailed $event): void
    {
        if ($event->report->isSubscriptionExpired()) {
            $event->subscription->delete();
        }
    }
}
