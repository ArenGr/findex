<?php

namespace App\Models;

use App\Services\Cache\RateCache;
use App\Support\OpeningHours;
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

    /**
     * The whole week, collapsed into the runs a person would actually read:
     * "Mon - Fri 09:30-17:30", "Sat 10:00-14:00", "Sun closed" - rather than
     * seven near-identical lines.
     *
     * Days are returned as keys, not names, so the view translates them.
     * A day the bank never published is left out of its run entirely: it is
     * neither open nor closed as far as we know, and both would be a claim
     * this app cannot support (see App\Support\OpeningHours).
     *
     * @return array<int, array{from: string, to: string, hours: array{0: string, 1: string}|null}>
     */
    public function weeklyHours(): array
    {
        if (! $this->hasOpeningHours()) {
            return [];
        }

        $runs = [];

        foreach (OpeningHours::DAYS as $day) {
            if (! array_key_exists($day, $this->opening_hours)) {
                continue;
            }

            $hours = $this->opening_hours[$day];
            $hours = is_array($hours) && count($hours) === 2 ? array_values($hours) : null;

            $last = $runs === [] ? null : $runs[count($runs) - 1];

            // Extend the run only if it ends on the day before this one -
            // a gap in the middle of the week must not be spanned.
            if ($last !== null
                && $last['hours'] === $hours
                && $this->isNextDay($last['to'], $day)) {
                $runs[count($runs) - 1]['to'] = $day;

                continue;
            }

            $runs[] = ['from' => $day, 'to' => $day, 'hours' => $hours];
        }

        return $runs;
    }

    private function isNextDay(string $previous, string $day): bool
    {
        $days = OpeningHours::DAYS;

        return array_search($day, $days, true) === array_search($previous, $days, true) + 1;
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
