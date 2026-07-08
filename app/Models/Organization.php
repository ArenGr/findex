<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Organization extends Authenticatable
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'website',
        'logo',
        'description',
        'country_code',
        'is_active',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

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
     * Get all mortgage offers from this organization.
     */
    public function mortgageOffers(): HasMany
    {
        return $this->hasMany(MortgageOffer::class);
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
     * Get all reviews for this organization.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)->latest();
    }

    public function averageRating(): ?float
    {
        return $this->reviews()->avg('rating');
    }

    public function reviewsCount(): int
    {
        return $this->reviews()->count();
    }

    /**
     * Eager-load `reviews_avg_rating` and `reviews_count` in a single query,
     * for listing many organizations at once (homepage teaser, directory)
     * without an N+1 query per organization.
     */
    #[Scope]
    protected function withRatingStats(Builder $query): Builder
    {
        return $query->withCount('reviews')->withAvg('reviews', 'rating');
    }

    /**
     * Get all branches for this organization.
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * Get all report requests for this organization.
     */
    public function reportRequests(): HasMany
    {
        return $this->hasMany(ReportRequest::class);
    }

    /**
     * Get all generated reports for this organization.
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
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
