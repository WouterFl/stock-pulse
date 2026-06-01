<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $fillable = [
        'ticker',
        'exchange',
        'name',
        'currency',
        'sector',
        'industry',
        'logo_url',
        'is_active',
        'polling_interval_seconds',
        'alert_threshold_percent',
        'alert_use_statistical',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'alert_use_statistical' => 'boolean',
            'polling_interval_seconds' => 'integer',
            'alert_threshold_percent' => 'decimal:2',
        ];
    }

    /**
     * Forceer tickers naar uppercase, zodat matching en uniciteit consistent zijn.
     */
    protected function setTickerAttribute(string $value): void
    {
        $this->attributes['ticker'] = Str::upper(trim($value));
    }

    protected function setExchangeAttribute(string $value): void
    {
        $this->attributes['exchange'] = Str::upper(trim($value));
    }

    protected function setCurrencyAttribute(?string $value): void
    {
        $this->attributes['currency'] = $value ? Str::upper(trim($value)) : $value;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * De meest recente koers, op basis van fetched_at.
     */
    public function latestQuote(): HasOne
    {
        return $this->hasOne(Quote::class)->latestOfMany('fetched_at');
    }

    public function newsArticles(): BelongsToMany
    {
        return $this->belongsToMany(NewsArticle::class)
            ->withPivot('match_type')
            ->withTimestamps();
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }
}
