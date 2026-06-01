<?php

namespace App\Listeners;

use NotificationChannels\WebPush\Events\NotificationSent;

/**
 * Werkt updated_at bij na een succesvolle push-delivery, zodat
 * `push:cleanup-stale` inactieve subscriptions kan herkennen (SP-32).
 */
class TouchPushSubscriptionOnSent
{
    public function handle(NotificationSent $event): void
    {
        $event->subscription->forceFill(['updated_at' => now()])->save();
    }
}
