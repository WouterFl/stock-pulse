<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Absolute drempel
    |--------------------------------------------------------------------------
    |
    | Standaard %-beweging (binnen het window) die een alert triggert.
    | Per bedrijf override-baar via Company.alert_threshold_percent.
    |
    */
    'default_threshold_percent' => (float) env('ALERT_DEFAULT_THRESHOLD_PERCENT', 3.0),

    // Tijdvenster (minuten) waarover de beweging wordt gemeten.
    'default_window_minutes' => (int) env('ALERT_DEFAULT_WINDOW_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Statistische detectie (2σ)
    |--------------------------------------------------------------------------
    |
    | Aantal periodes voor mean/stddev en het aantal sigma waarboven een
    | beweging als uitschieter geldt. Alleen actief als
    | Company.alert_use_statistical = true.
    |
    */
    'statistical_periods' => (int) env('ALERT_STATISTICAL_PERIODS', 20),
    'statistical_sigma' => (float) env('ALERT_STATISTICAL_SIGMA', 2.0),

    /*
    |--------------------------------------------------------------------------
    | Cooldown
    |--------------------------------------------------------------------------
    |
    | Geen tweede absolute alert binnen dit aantal minuten voor hetzelfde
    | bedrijf (default: gelijk aan het window), om spam te voorkomen.
    |
    */
    'cooldown_minutes' => (int) env('ALERT_COOLDOWN_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Severity-drempels
    |--------------------------------------------------------------------------
    |
    | Bepaalt de severity op basis van |change_percent| t.o.v. de drempel.
    | warning = >= drempel, critical = >= drempel * factor.
    |
    */
    'critical_multiplier' => (float) env('ALERT_CRITICAL_MULTIPLIER', 2.0),
];
