<?php

namespace App\Models;

use App\Services\Cache\OrgRatingsCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Review extends Model
{
    protected static function booted(): void
    {
        static::saved(fn () => OrgRatingsCache::invalidate());
        static::deleted(fn () => OrgRatingsCache::invalidate());
    }

    protected $fillable = [
        'organization_id',
        'user_id',
        'guest_name',
        'branch_id',
        'rating',
        'comment',
    ];

    protected $casts = [
        'rating' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The account name if the review was left while signed in, otherwise
     * the display name the guest reviewer typed in.
     */
    public function getReviewerNameAttribute(): string
    {
        return $this->user->name ?? $this->guest_name;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function reply(): HasOne
    {
        return $this->hasOne(ReviewReply::class);
    }
}
