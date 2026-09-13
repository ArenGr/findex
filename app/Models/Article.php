<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'writer_id',
        'title',
        'slug',
        'language',
        'excerpt',
        'body',
        'featured_image',
        'status',
        'rejection_reason',
        'reviewed_by',
        'published_at',
    ];

    protected $casts = [
        'status' => ArticleStatus::class,
        'published_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function writer(): BelongsTo
    {
        return $this->belongsTo(Writer::class);
    }

    // The admin who approved or rejected this article - null until it's been through review.
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isDraft(): bool
    {
        return $this->status === ArticleStatus::DRAFT;
    }

    public function isRejected(): bool
    {
        return $this->status === ArticleStatus::REJECTED;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ArticleStatus::APPROVED);
    }

    public function getFeaturedImageUrlAttribute(): ?string
    {
        return $this->featured_image ? Storage::disk('public')->url($this->featured_image) : null;
    }

    public function summary(): string
    {
        return $this->excerpt ?: Str::limit(strip_tags($this->body), 160);
    }
}
