<?php

namespace App\Events;

use App\Models\Quote;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Bonus (SP-22): live koers-updates naar een channel per bedrijf, zodat de
 * detailpagina realtime kan bijwerken.
 */
class QuoteUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Quote $quote) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('company.'.$this->quote->company_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'QuoteUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'company_id' => $this->quote->company_id,
            'price' => (float) $this->quote->price,
            'change_percent' => $this->quote->change_percent !== null ? (float) $this->quote->change_percent : null,
            'source' => $this->quote->source,
            'fetched_at' => $this->quote->fetched_at?->toIso8601String(),
        ];
    }
}
