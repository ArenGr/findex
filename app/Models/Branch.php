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

    // Scope a query to only include active branches.
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', 1);
    }

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

    public const TIMEZONE = 'Asia/Yerevan';

    /**
     * The opening and closing time for a given day, or null when the branch is shut.
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
