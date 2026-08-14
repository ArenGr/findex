<?php

namespace App\Models;

use App\Services\Cache\RateCache;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
        'opening_hours',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'opening_hours' => 'array',
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

    /**
     * Armenia does not observe daylight saving, but the app runs on UTC - so
     * "open now" has to be asked in Yerevan's own time or every branch would
     * appear to close four hours early.
     */
    public const TIMEZONE = 'Asia/Yerevan';

    /**
     * The opening and closing time for a given day, or null when the branch is
     * shut. Returns null just as readily when we have no hours on file at all,
     * so callers must check hasOpeningHours() first if the difference matters -
     * "closed" and "we do not know" are not the same claim to make.
     *
     * @return array{0: string, 1: string}|null
     */
    public function hoursOn(CarbonInterface $moment): ?array
    {
        $day = strtolower($moment->copy()->setTimezone(self::TIMEZONE)->format('D'));

        $hours = $this->opening_hours[$day] ?? null;

        return is_array($hours) && count($hours) === 2 ? array_values($hours) : null;
    }

    public function hasOpeningHours(): bool
    {
        return is_array($this->opening_hours) && $this->opening_hours !== [];
    }

    /**
     * Null when we have no hours for this branch - the caller decides whether
     * to say "closed" or to say nothing, and saying "closed" about a branch we
     * simply have no data for would send someone away from an open door.
     */
    public function isOpenAt(?CarbonInterface $moment = null): ?bool
    {
        if (! $this->hasOpeningHours()) {
            return null;
        }

        $moment = ($moment ?? Carbon::now())->copy()->setTimezone(self::TIMEZONE);
        $hours = $this->hoursOn($moment);

        if ($hours === null) {
            return false;
        }

        [$opens, $closes] = $hours;
        $minutes = (int) $moment->format('G') * 60 + (int) $moment->format('i');

        return $minutes >= $this->toMinutes($opens) && $minutes < $this->toMinutes($closes);
    }

    /** "18:00" as minutes past midnight. */
    private function toMinutes(string $time): int
    {
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');

        return ((int) $hour) * 60 + (int) $minute;
    }
}
