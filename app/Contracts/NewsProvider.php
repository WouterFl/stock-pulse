<?php

namespace App\Contracts;

use App\Support\News\NewsArticleData;

interface NewsProvider
{
    /**
     * Unieke, leesbare naam van de provider (voor logs).
     */
    public function name(): string;

    /**
     * Is deze provider bruikbaar? (bv. API-key aanwezig)
     */
    public function isConfigured(): bool;

    /**
     * Haal de meest recente artikelen op.
     *
     * @return array<int, NewsArticleData>
     */
    public function fetch(): array;
}
