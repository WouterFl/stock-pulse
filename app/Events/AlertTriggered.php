<?php

namespace App\Events;

use App\Models\Alert;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AlertTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Alert $alert) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('alerts'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'AlertTriggered';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->alert->loadMissing('company');

        return [
            'id' => $this->alert->id,
            'company_id' => $this->alert->company_id,
            'ticker' => $this->alert->company?->ticker,
            'type' => $this->alert->type,
            'severity' => $this->alert->severity,
            'title' => $this->alert->title,
            'description' => $this->alert->shortDescription(),
            'triggered_at' => $this->alert->triggered_at?->toIso8601String(),
        ];
    }
}
