<?php

use App\Jobs\FetchNewsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// --- Koersdata (Sprint 2) ---
// Elke minuut: dispatch fetch-jobs voor bedrijven die aan hun interval toe zijn.
Schedule::command('quotes:dispatch')
    ->everyMinute()
    ->withoutOverlapping();

// Dagelijks om 03:00: oude koersen opschonen (SP-10).
Schedule::command('quotes:prune')
    ->dailyAt('03:00');

// --- Nieuws (Sprint 3) ---
// Elke N minuten (config/news.php) de nieuws-aggregatie draaien op de news-queue.
Schedule::job(new FetchNewsJob)
    ->cron('*/'.max(1, (int) config('news.interval_minutes', 5)).' * * * *')
    ->withoutOverlapping();

// --- Push (Sprint 5) ---
// Wekelijks: verouderde push-subscriptions (>90 dagen inactief) opruimen (SP-32).
Schedule::command('push:cleanup-stale')
    ->weekly();
