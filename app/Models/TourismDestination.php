<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourismDestination extends Model
{
    protected $fillable = [
        'organization_id',
        'country_code',
        'is_paused',
        'paused_until',
    ];

    protected $casts = [
        'is_paused' => 'boolean',
        'paused_until' => 'date',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * False while paused, unless paused_until has passed - lets a partner
     * set an auto-resume date instead of having to remember to unpause
     * manually (see SendQuoteRequestToPartnersJob's matching query, which
     * uses the same condition to exclude paused destinations).
     */
    public function isActive(): bool
    {
        if (!$this->is_paused) {
            return true;
        }

        return $this->paused_until !== null && $this->paused_until->isPast();
    }
}
