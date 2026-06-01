<?php

namespace App\Notifications;

use App\Models\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class AlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Alert $alert)
    {
        // Eigen queue zodat trage push-services de quote-pipeline niet ophouden.
        $this->onQueue('push');
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $this->alert->loadMissing('company');

        $message = (new WebPushMessage)
            ->title("{$this->alert->company?->ticker}: {$this->alert->title}")
            ->icon('/icons/icon-192.png')
            ->badge('/icons/badge-72.png')
            ->body($this->alert->shortDescription())
            ->tag("alert-{$this->alert->company_id}") // dedup per bedrijf
            ->data(['url' => "/admin/alerts?highlight={$this->alert->id}"])
            ->options(['TTL' => 3600]);

        // Severity-mapping: critical vereist interactie.
        if ($this->alert->severity === 'critical') {
            $message->requireInteraction();
            $message->vibrate([200, 100, 200]);
        }

        return $message;
    }
}
