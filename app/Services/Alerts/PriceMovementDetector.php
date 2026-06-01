<?php

namespace App\Services\Alerts;

use App\Models\Alert;
use App\Models\Company;
use App\Models\Quote;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Detecteert significante koersbewegingen voor een bedrijf en maakt
 * Alert-records aan. Twee strategieën:
 *
 *  - absolute_threshold: |beweging| over het window >= drempel (met cooldown)
 *  - statistical_outlier: huidige beweging > mean + sigma·stddev (opt-in per bedrijf)
 */
class PriceMovementDetector
{
    /**
     * @return Collection<int, Alert> de aangemaakte alerts
     */
    public function detect(Company $company, Quote $latest): Collection
    {
        $created = collect();

        if ($absolute = $this->detectAbsolute($company, $latest)) {
            $created->push($absolute);
        }

        if ($company->alert_use_statistical && ($statistical = $this->detectStatistical($company, $latest))) {
            $created->push($statistical);
        }

        return $created;
    }

    private function detectAbsolute(Company $company, Quote $latest): ?Alert
    {
        $threshold = $company->alert_threshold_percent !== null
            ? (float) $company->alert_threshold_percent
            : (float) config('alerts.default_threshold_percent');

        $windowMinutes = (int) config('alerts.default_window_minutes');
        $windowStart = $latest->fetched_at->copy()->subMinutes($windowMinutes);

        // Oudste quote binnen het window vormt het referentiepunt.
        $reference = Quote::query()
            ->where('company_id', $company->id)
            ->where('fetched_at', '>=', $windowStart)
            ->where('fetched_at', '<=', $latest->fetched_at)
            ->orderBy('fetched_at')
            ->first();

        if ($reference === null || (float) $reference->price == 0.0 || $reference->is($latest)) {
            return null;
        }

        $changePercent = round(((float) $latest->price - (float) $reference->price) / (float) $reference->price * 100, 4);

        if (abs($changePercent) < $threshold) {
            return null;
        }

        // Cooldown: geen tweede absolute alert binnen cooldown_minutes.
        if ($this->inCooldown($company, 'absolute_threshold', (int) config('alerts.cooldown_minutes', $windowMinutes), $latest->fetched_at)) {
            return null;
        }

        return $this->createAlert($company, 'absolute_threshold', $this->severityFor(abs($changePercent), $threshold), [
            'from' => (float) $reference->price,
            'to' => (float) $latest->price,
            'change_percent' => $changePercent,
            'window_minutes' => $windowMinutes,
        ], $latest->fetched_at);
    }

    private function detectStatistical(Company $company, Quote $latest): ?Alert
    {
        $periods = (int) config('alerts.statistical_periods');
        $sigma = (float) config('alerts.statistical_sigma');

        // Pak de laatste N change_percent-waarden vóór de huidige quote.
        $changes = Quote::query()
            ->where('company_id', $company->id)
            ->where('id', '<', $latest->id)
            ->whereNotNull('change_percent')
            ->orderByDesc('fetched_at')
            ->limit($periods)
            ->pluck('change_percent')
            ->map(fn ($v) => (float) $v);

        if ($changes->count() < max(3, (int) ceil($periods / 2))) {
            return null; // te weinig data voor betrouwbare statistiek
        }

        $mean = $changes->avg();
        $stddev = $this->stddev($changes, $mean);

        if ($stddev <= 0.0) {
            return null;
        }

        $current = $latest->change_percent !== null ? (float) $latest->change_percent : null;

        if ($current === null) {
            return null;
        }

        $upper = $mean + $sigma * $stddev;
        $lower = $mean - $sigma * $stddev;

        if ($current <= $upper && $current >= $lower) {
            return null;
        }

        if ($this->inCooldown($company, 'statistical_outlier', (int) config('alerts.cooldown_minutes'), $latest->fetched_at)) {
            return null;
        }

        $zScore = round(($current - $mean) / $stddev, 2);

        return $this->createAlert($company, 'statistical_outlier', abs($zScore) >= ($sigma * 1.5) ? 'critical' : 'warning', [
            'change_percent' => $current,
            'mean' => round($mean, 4),
            'stddev' => round($stddev, 4),
            'sigma' => $sigma,
            'z_score' => $zScore,
            'periods' => $changes->count(),
        ], $latest->fetched_at);
    }

    private function inCooldown(Company $company, string $type, int $minutes, CarbonInterface $now): bool
    {
        return Alert::query()
            ->where('company_id', $company->id)
            ->where('type', $type)
            ->where('triggered_at', '>=', $now->copy()->subMinutes($minutes))
            ->exists();
    }

    private function severityFor(float $absChange, float $threshold): string
    {
        $multiplier = (float) config('alerts.critical_multiplier', 2.0);

        return $absChange >= $threshold * $multiplier ? 'critical' : 'warning';
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function createAlert(Company $company, string $type, string $severity, array $details, CarbonInterface $triggeredAt): Alert
    {
        $sign = ($details['change_percent'] ?? 0) >= 0 ? '+' : '';
        $title = sprintf(
            '%s %s%.2f%%%s',
            $company->ticker,
            $sign,
            (float) ($details['change_percent'] ?? 0),
            isset($details['window_minutes']) ? " in {$details['window_minutes']}m" : '',
        );

        return Alert::create([
            'company_id' => $company->id,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'details' => $details,
            'triggered_at' => $triggeredAt,
        ]);
    }

    /**
     * @param  Collection<int, float>  $values
     */
    private function stddev(Collection $values, float $mean): float
    {
        $variance = $values->reduce(fn ($carry, $v) => $carry + ($v - $mean) ** 2, 0.0) / $values->count();

        return sqrt($variance);
    }
}
