<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only koers-tijdsreeks. Records worden nooit geüpdatet, dus geen updated_at.
 */
class Quote extends Model
{
    // Alleen created_at bijhouden; updated_at uitschakelen.
    const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'price',
        'open',
        'high',
        'low',
        'previous_close',
        'volume',
        'change_percent',
        'source',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:6',
            'open' => 'decimal:6',
            'high' => 'decimal:6',
            'low' => 'decimal:6',
            'previous_close' => 'decimal:6',
            'volume' => 'integer',
            'change_percent' => 'decimal:4',
            'fetched_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
