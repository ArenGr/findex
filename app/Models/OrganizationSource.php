<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationSource extends Model
{
    protected $table = 'organization_sources';

    protected $fillable = [
        'organization_id',
        'source_type',
        'url',
        'is_active',
        'last_scraped_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_scraped_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the organization that owns this source.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the full URL for this source.
     */
    public function getFullUrl(): string
    {
        $baseUrl = $this->organization->website;
        $path = $this->url;

        // If URL is already absolute, return as is
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        // Otherwise, combine base URL with path
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Mark this source as just scraped.
     */
    public function markAsScraped(): void
    {
        $this->update(['last_scraped_at' => now()]);
    }
}
