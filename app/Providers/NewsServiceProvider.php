<?php

namespace App\Providers;

use App\Services\News\CompanyArticleMatcher;
use App\Services\News\NewsIngester;
use Illuminate\Support\ServiceProvider;

class NewsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NewsIngester::class, function ($app) {
            $providers = array_map(
                fn (string $class) => $app->make($class),
                config('news.providers', []),
            );

            return new NewsIngester($providers, $app->make(CompanyArticleMatcher::class));
        });
    }
}
