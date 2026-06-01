<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Alle ingelogde (admin) gebruikers mogen de alerts-feed ontvangen (SP-22).
Broadcast::channel('alerts', fn ($user) => $user !== null);

// Per-bedrijf koers-updates (bonus SP-22): channel per company.
Broadcast::channel('company.{companyId}', fn ($user, int $companyId) => $user !== null);
