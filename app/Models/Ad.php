<?php

namespace App\Models;

use App\Enums\AdPlacement;
use App\Enums\AdSide;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Ad extends Model
{
    protected $fillable = [
        'placement',
        'side',
        'advertiser',
        'initials',
        'logo',
        'headline',
        'body',
        'cta_label',
        'href',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'placement' => AdPlacement::class,
        'side' => AdSide::class,
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Public URL for the uploaded logo, or null if none was set - the
     * `logo` column only stores the disk-relative path Filament's
     * FileUpload writes.
     */
    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? Storage::disk('public')->url($this->logo) : null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForPlacement($query, AdPlacement|string $placement)
    {
        return $query->where('placement', $placement);
    }
}
