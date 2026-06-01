<?php

namespace App\Providers;

use App\Services\Quotes\QuoteFetcher;
use Illuminate\Support\ServiceProvider;

class QuoteServiceProvider extends ServiceProvider
{
    /**
     * Registreer de QuoteFetcher met de providers in de prioriteitsvolgorde
     * uit config/quotes.php.
     */
    public function register(): void
    {
        $this->app->singleton(QuoteFetcher::class, function ($app) {
            $providers = array_map(
                fn (string $class) => $app->make($class),
                config('quotes.providers', []),
            );

            return new QuoteFetcher($providers);
        });
    }
}
