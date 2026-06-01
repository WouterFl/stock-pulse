<?php

namespace App\Listeners;

use App\Events\AlertTriggered;
use App\Models\User;
use App\Notifications\AlertNotification;

/**
 * Stuurt bij een nieuwe alert een web-push naar elke gebruiker die push aan
 * heeft staan en wiens voorkeuren deze alert toelaten (SP-30/SP-31).
 */
class SendAlertPushNotifications
{
    public function handle(AlertTriggered $event): void
    {
        $alert = $event->alert;

        // Alleen gebruikers met minstens één actieve push-subscription.
        User::query()
            ->whereHas('pushSubscriptions')
            ->get()
            ->each(function (User $user) use ($alert) {
                if ($user->notificationPreferences()->allowsPush($alert)) {
                    $user->notify(new AlertNotification($alert));
                }
            });
    }
}
