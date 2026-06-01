<?php

namespace App\Services\News;

use App\Contracts\NewsProvider;
use App\Models\Company;
use App\Models\NewsArticle;
use App\Support\News\NewsArticleData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Haalt artikelen uit alle geconfigureerde providers, dedupliceert op url_hash,
 * matcht ze aan bedrijven en schrijft ze (transactioneel) weg.
 */
class NewsIngester
{
    /**
     * @param  iterable<NewsProvider>  $providers
     */
    public function __construct(
        private iterable $providers,
        private CompanyArticleMatcher $matcher,
    ) {}

    /**
     * @return array{fetched: int, created: int, linked: int}
     */
    public function run(): array
    {
        $companies = Company::query()->active()->get();

        /** @var array<string, NewsArticleData> $batch */
        $batch = [];

        // 1. Verzamel uit alle providers, dedupliceer binnen de batch op url_hash.
        foreach ($this->providers as $provider) {
            if (! $provider->isConfigured()) {
                continue;
            }

            foreach ($provider->fetch() as $article) {
                // First-wins: behoud de eerste (vaak rijkste, bv. api-tagged) variant.
                $batch[$article->urlHash()] ??= $article;
            }
        }

        $fetched = count($batch);

        if ($fetched === 0) {
            return ['fetched' => 0, 'created' => 0, 'linked' => 0];
        }

        // 2. Filter artikelen die al in de DB staan.
        $existing = NewsArticle::query()
            ->whereIn('url_hash', array_keys($batch))
            ->pluck('url_hash')
            ->all();

        $new = array_diff_key($batch, array_flip($existing));

        $created = 0;
        $linked = 0;

        // 3. Schrijf nieuwe artikelen weg + koppel aan bedrijven.
        foreach ($new as $data) {
            DB::transaction(function () use ($data, $companies, &$created, &$linked) {
                /** @var NewsArticle $article */
                $article = NewsArticle::create($data->toArticleAttributes());
                $created++;

                $matches = $this->matcher->match($data, $companies);

                if ($matches !== []) {
                    $pivot = [];
                    foreach ($matches as $companyId => $matchType) {
                        $pivot[$companyId] = ['match_type' => $matchType];
                    }
                    $article->companies()->sync($pivot);
                    $linked += count($pivot);
                }
            });
        }

        Log::info('NewsIngester voltooid', compact('fetched', 'created', 'linked'));

        return ['fetched' => $fetched, 'created' => $created, 'linked' => $linked];
    }
}
