<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class NewsArticle extends Model
{
    protected $fillable = [
        'source',
        'external_id',
        'url',
        'url_hash',
        'title',
        'description',
        'image_url',
        'published_at',
        'language',
        'sentiment',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'sentiment' => 'decimal:3',
        ];
    }

    /**
     * Bereken de url_hash automatisch bij het zetten van de url.
     */
    protected function setUrlAttribute(string $value): void
    {
        $this->attributes['url'] = $value;
        $this->attributes['url_hash'] = hash('sha256', $value);
    }

    public static function hashFor(string $url): string
    {
        return hash('sha256', $url);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->withPivot('match_type')
            ->withTimestamps();
    }

    public function scopeForCompany($query, Company $company)
    {
        return $query->whereHas('companies', fn ($q) => $q->whereKey($company->getKey()));
    }
}
