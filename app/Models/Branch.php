<?php

namespace App\Models;

use App\Services\Cache\RateCache;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    // Only the fields the 'rates' cache actually reads (RateController's
    // city filter) trigger an invalidation - a branch's name/address
    // changing shouldn't flush rate data that doesn't depend on it.
    protected static function booted(): void
    {
        static::saved(function (self $branch) {
            if ($branch->wasChanged(['is_active', 'city'])) {
                RateCache::invalidate();
            }
        });
        static::deleted(fn () => RateCache::invalidate());
    }

    protected $fillable = [
        'organization_id',
        'name',
        'address',
        'city',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Scope a query to only include active branches.
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', 1);
    }

    /**
     * Great-circle (haversine) distance to a point, in kilometers - null if
     * this branch has no coordinates yet (an org that's only entered a city
     * name, not pinned an exact location). Computed in PHP rather than raw
     * SQL trig functions so RateController's "find nearby" sort works
     * identically against both MySQL (production) and SQLite (tests)
     * without a driver-specific query.
     */
    public function distanceInKmFrom(float $latitude, float $longitude): ?float
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        $earthRadiusKm = 6371;

        $latDelta = deg2rad($latitude - $this->latitude);
        $lngDelta = deg2rad($longitude - $this->longitude);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($this->latitude)) * cos(deg2rad($latitude)) * sin($lngDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
