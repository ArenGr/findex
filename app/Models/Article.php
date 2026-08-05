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

    /**
     * reviewed_by/published_at are here for legitimate trusted server-side
     * code (the admin approve/reject actions, factories, seeders) to set via
     * mass assignment - the actual guard against a writer tampering with
     * them is at the controller layer (Writer\ArticleController::validated()
     * never extracts these from request input), same trust model as Ad.
     */
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

    /**
     * Public URLs use the slug, not the id (see routes/web/public/articles.php
     * and route('articles.show', $article)) - the admin panel deliberately
     * overrides back to id (ArticleResource::$recordRouteKeyName), same
     * convention as Writer/WriterResource.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function writer(): BelongsTo
    {
        return $this->belongsTo(Writer::class);
    }

    /**
     * The admin who approved or rejected this article - null until it's
     * been through review.
     */
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

    /**
     * Approved articles are live on the public site immediately - there's
     * no separate publish/schedule step, so "approved" and "published" are
     * the same state.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ArticleStatus::APPROVED);
    }

    /**
     * Public URL for the uploaded featured image, or null if none was set -
     * mirrors Ad::getLogoUrlAttribute().
     */
    public function getFeaturedImageUrlAttribute(): ?string
    {
        return $this->featured_image ? Storage::disk('public')->url($this->featured_image) : null;
    }

    /**
     * Short teaser for cards and <meta name="description"> - the writer's
     * own excerpt if they wrote one, otherwise a trimmed plain-text lead-in
     * to the body.
     */
    public function summary(): string
    {
        return $this->excerpt ?: Str::limit(strip_tags($this->body), 160);
    }
}
