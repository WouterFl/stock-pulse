<?php

namespace App\Support\Notifications;

use App\Models\Alert;
use Illuminate\Support\Carbon;

/**
 * Waarde-object rond de per-user notificatie-voorkeuren (opgeslagen als JSON
 * op users.notification_preferences). Bepaalt of een alert via push mag.
 */
final class NotificationPreferences
{
    private const SEVERITY_RANK = ['info' => 0, 'warning' => 1, 'critical' => 2];

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(private array $data) {}

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'push_enabled' => true,
            'types' => [
                'absolute_threshold' => true,
                'statistical_outlier' => true,
                'news_spike' => true,
            ],
            // Minimale severity die nog gepusht wordt. 'info' wordt nooit gepusht.
            'min_severity' => 'warning',
            'quiet_hours' => [
                'enabled' => false,
                'start' => '23:00',
                'end' => '07:00',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function fromArray(?array $data): self
    {
        return new self(array_replace_recursive(self::defaults(), $data ?? []));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    public function pushEnabled(): bool
    {
        return (bool) ($this->data['push_enabled'] ?? true);
    }

    /**
     * Bepaalt of een alert via push verstuurd mag worden.
     */
    public function allowsPush(Alert $alert, ?Carbon $now = null): bool
    {
        if (! $this->pushEnabled()) {
            return false;
        }

        // 'info' gaat nooit via push (alleen in-app feed).
        if ($alert->severity === 'info') {
            return false;
        }

        // Categorie-toggle.
        if (! ($this->data['types'][$alert->type] ?? true)) {
            return false;
        }

        // Minimale severity.
        $min = self::SEVERITY_RANK[$this->data['min_severity'] ?? 'warning'] ?? 1;
        if ((self::SEVERITY_RANK[$alert->severity] ?? 0) < $min) {
            return false;
        }

        // Quiet hours.
        if ($this->inQuietHours($now ?? Carbon::now())) {
            return false;
        }

        return true;
    }

    public function inQuietHours(Carbon $now): bool
    {
        $quiet = $this->data['quiet_hours'] ?? [];

        if (! ($quiet['enabled'] ?? false)) {
            return false;
        }

        $start = $quiet['start'] ?? '23:00';
        $end = $quiet['end'] ?? '07:00';
        $current = $now->format('H:i');

        // Interval kan over middernacht lopen (bv. 23:00–07:00).
        if ($start <= $end) {
            return $current >= $start && $current < $end;
        }

        return $current >= $start || $current < $end;
    }
}
