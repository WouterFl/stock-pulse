<?php

namespace App\Services\News;

use App\Models\Company;
use App\Support\News\NewsArticleData;
use Illuminate\Support\Collection;

/**
 * Koppelt een nieuwsartikel aan bedrijven via drie strategieën, in volgorde
 * van betrouwbaarheid: api_tagged > ticker > name.
 *
 * Bekende valkuilen (zie SP-15): generieke bedrijfsnamen ("Shell", "Apple")
 * matchen ook niet-financiële context. Mogelijke mitigatie later: een
 * confidence-score + handmatige review-queue. Tickers van 2+ chars worden
 * case-sensitive gematcht om ruis als "IT"/"AT" te beperken.
 */
class CompanyArticleMatcher
{
    /**
     * @param  Collection<int, Company>  $companies
     * @return array<int, string> map van company_id => match_type
     */
    public function match(NewsArticleData $article, Collection $companies): array
    {
        $matches = [];
        $haystack = trim($article->title.' '.($article->description ?? ''));
        $haystackLower = mb_strtolower($haystack);
        $apiTickers = array_map('strtoupper', $article->tickers);

        foreach ($companies as $company) {
            $ticker = strtoupper($company->ticker);

            // 1. API-tagged: de bron gaf deze ticker al expliciet mee.
            if (in_array($ticker, $apiTickers, true)) {
                $matches[$company->id] = 'api_tagged';

                continue;
            }

            // 2. Ticker-match: case-sensitive whole-word match in titel/omschrijving.
            if ($this->matchesTicker($ticker, $haystack)) {
                $matches[$company->id] = 'ticker';

                continue;
            }

            // 3. Naam-match: bedrijfsnaam als whole word, case-insensitive.
            if ($this->matchesName($company->name, $haystackLower)) {
                $matches[$company->id] = 'name';
            }
        }

        return $matches;
    }

    private function matchesTicker(string $ticker, string $haystack): bool
    {
        if ($ticker === '') {
            return false;
        }

        // Whole-word, case-sensitive (tickers zijn uppercase).
        return (bool) preg_match('/(?<![A-Za-z0-9.])'.preg_quote($ticker, '/').'(?![A-Za-z0-9.])/', $haystack);
    }

    private function matchesName(string $name, string $haystackLower): bool
    {
        $name = $this->normaliseName($name);

        // Te korte/generieke namen overslaan om ruis te beperken.
        if (mb_strlen($name) < 3) {
            return false;
        }

        return (bool) preg_match('/(?<![\p{L}])'.preg_quote(mb_strtolower($name), '/').'(?![\p{L}])/u', $haystackLower);
    }

    /**
     * Strip leestekens + veelvoorkomende rechtsvormen, zodat "Apple Inc." → "Apple"
     * en "Shell plc" → "Shell".
     */
    private function normaliseName(string $name): string
    {
        // 1. Leestekens (punten, komma's, &) vervangen door spaties.
        $clean = preg_replace('/[^\p{L}\p{N} ]+/u', ' ', $name);

        // 2. Bekende rechtsvorm-tokens verwijderen.
        $clean = preg_replace('/\b(inc|corp|corporation|nv|plc|holding|ltd|sa|ag|co)\b/i', '', (string) $clean);

        return trim(preg_replace('/\s+/', ' ', (string) $clean));
    }
}
