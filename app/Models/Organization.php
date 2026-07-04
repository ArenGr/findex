<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'website',
        'logo',
        'country_code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all sources for this organization.
     */
    public function sources(): HasMany
    {
        return $this->hasMany(OrganizationSource::class);
    }

    /**
     * Get all currency rates from this organization.
     */
    public function currencyRates(): HasMany
    {
        return $this->hasMany(CurrencyRate::class);
    }

    /**
     * Get all scraping jobs for this organization.
     */
    public function scrapingJobs(): HasMany
    {
        return $this->hasMany(ScrapingJob::class);
    }

    /**
     * Get the active sources for this organization.
     */
    public function activeSources(): HasMany
    {
        return $this->sources()->where('is_active', true);
    }

    /**
     * Get the latest currency rates.
     */
    public function latestCurrencyRates(): HasMany
    {
        return $this->currencyRates()->where('scraped_at', '>=', now()->subHours(24));
    }

    /**
     * Scope a query to only include active organizations.
     *
     * @param Builder $query
     * @return Builder
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', 1);
    }
}
