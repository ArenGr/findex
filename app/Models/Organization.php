<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use SoftDeletes;

    public const TYPES = ['bank', 'exchange', 'insurance', 'tourism', 'other'];

    /**
     * Types that deal in currency rates - the only ones with a reason to see
     * the dashboard's Rates page (see hasRatesPage()).
     */
    public const RATES_TYPES = ['bank', 'exchange'];

    /**
     * Types that fulfil travel quote requests - the only ones with a reason
     * to see the dashboard's Tourism page (see hasTourismPage()).
     */
    public const TOURISM_TYPES = ['tourism'];

    protected $fillable = [
        'name',
        'slug',
        'type',
        'website',
        'logo',
        'description_hy',
        'description_en',
        'description_ru',
        'country_code',
        'is_active',
        'telegram_chat_id',
        'telegram_connect_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
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
     * Destination countries this organization (type: tourism) can quote for.
     */
    public function tourismDestinations(): HasMany
    {
        return $this->hasMany(TourismDestination::class);
    }

    /**
     * Quote requests this organization has been asked to reply to.
     */
    public function quoteResponses(): HasMany
    {
        return $this->hasMany(QuoteResponse::class);
    }

    /**
     * Auto insurance quotes this organization (type: insurance) has provided.
     */
    public function autoInsuranceQuotes(): HasMany
    {
        return $this->hasMany(AutoInsuranceQuote::class);
    }

    /**
     * Staff accounts that can log in on this organization's behalf (guard
     * 'organization', role 'organization') - see User::organization().
     * A HasMany rather than a single owner so multiple staff logins per
     * org can be supported later without another schema change.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
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

    public function hasRatesPage(): bool
    {
        return in_array($this->type, self::RATES_TYPES, true);
    }

    public function hasTourismPage(): bool
    {
        return in_array($this->type, self::TOURISM_TYPES, true);
    }

    /**
     * The description in the current visitor's locale, falling back
     * through the site's default locale and then any other language the
     * org wrote one in - orgs serve customers across all of
     * config('localization.available') but often only write a
     * description in one language, and showing nothing is worse than
     * showing it in the wrong one. Named to match the dropped
     * `description` column so every existing read site (e.g.
     * organizations/show.blade.php) keeps working unchanged; the
     * dashboard profile edit form reads/writes description_hy/en/ru
     * directly instead, since it needs all of them at once.
     */
    public function getDescriptionAttribute(): ?string
    {
        $locales = array_unique([
            app()->getLocale(),
            config('localization.default'),
            ...array_keys(config('localization.available')),
        ]);

        foreach ($locales as $locale) {
            if (!empty($this->attributes["description_{$locale}"] ?? null)) {
                return $this->attributes["description_{$locale}"];
            }
        }

        return null;
    }
}
