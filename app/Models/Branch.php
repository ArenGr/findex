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
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
}
