<?php

namespace App\Models;

use App\Events\AlertTriggered;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Alert extends Model
{
    protected $fillable = [
        'company_id',
        'type',
        'severity',
        'title',
        'details',
        'triggered_at',
        'read_at',
    ];

    /**
     * Broadcast realtime zodra een alert wordt aangemaakt (SP-22).
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => AlertTriggered::class,
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'triggered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function newsArticles(): BelongsToMany
    {
        return $this->belongsToMany(NewsArticle::class)->withTimestamps();
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }

    /**
     * Korte, voor push/feed bruikbare omschrijving.
     */
    public function shortDescription(): string
    {
        $details = $this->details ?? [];

        if (isset($details['change_percent'], $details['window_minutes'])) {
            return sprintf(
                '%+.2f%% in %d min',
                (float) $details['change_percent'],
                (int) $details['window_minutes'],
            );
        }

        return $this->title;
    }
}
