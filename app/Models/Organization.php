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
        'min_lead_budget_amd',
        'min_lead_party_size',
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
     * Saved reply templates (see QuoteTemplate) this organization can
     * prefill the response form with instead of typing every offer from
     * scratch.
     */
    public function quoteTemplates(): HasMany
    {
        return $this->hasMany(QuoteTemplate::class);
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

    /**
     * Active, Telegram-connected tourism partners currently able to quote
     * for a destination - the single source of truth for "is anyone
     * available for this country right now", shared by
     * QuoteRequestController::store()'s pre-submit check and
     * SendQuoteRequestToPartnersJob's actual fan-out, so the two can't
     * silently drift out of sync (e.g. one honoring a paused destination
     * and the other not).
     *
     * $partySize/$budgetAmd apply a partner's own opt-in minimums
     * (min_lead_party_size/min_lead_budget_amd) - a partner with a
     * threshold set is excluded when the value is unknown (null), not just
     * when it's known to be too low, since an unverifiable lead is exactly
     * what the filter exists to keep out.
     */
    #[Scope]
    protected function tourismPartnersForDestination(Builder $query, string $countryCode, ?int $partySize = null, ?float $budgetAmd = null): Builder
    {
        return $query->active()
            ->where('type', 'tourism')
            ->whereNotNull('telegram_chat_id')
            // At least one team login has confirmed they actually control
            // the email they registered with - without this, anyone could
            // register an org under a business they don't own (using an
            // email they don't control, unverifiable) and start receiving
            // real customers' private trip details.
            ->whereHas('users', fn ($query) => $query->whereNotNull('email_verified_at'))
            ->whereHas('tourismDestinations', fn ($query) => $query
                ->where('country_code', $countryCode)
                ->where(fn ($query) => $query->where('is_paused', false)
                    ->orWhere('paused_until', '<', now())))
            ->where(function ($query) use ($partySize) {
                $query->whereNull('min_lead_party_size');
                if ($partySize !== null) {
                    $query->orWhere('min_lead_party_size', '<=', $partySize);
                }
            })
            ->where(function ($query) use ($budgetAmd) {
                $query->whereNull('min_lead_budget_amd');
                if ($budgetAmd !== null) {
                    $query->orWhere('min_lead_budget_amd', '<=', $budgetAmd);
                }
            });
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
     * Minimum sample sizes below which a badge would be noise rather than
     * signal (e.g. one lucky fast reply out of one lead isn't "fast").
     */
    public const FAST_RESPONDER_MAX_HOURS = 6;
    public const FAST_RESPONDER_MIN_RESPONSES = 3;
    public const TOP_RATED_MIN_RATING = 4.5;
    public const TOP_RATED_MIN_REVIEWS = 3;

    public function respondedQuoteResponses(): HasMany
    {
        return $this->quoteResponses()->where('status', QuoteResponse::STATUS_RESPONDED)->whereNotNull('responded_at');
    }

    /**
     * abs() as a defensive floor - created_at is always set before
     * responded_at in the normal request -> reply flow, but a negative
     * diff (clock skew, a manually-corrected row) should never surface as
     * a nonsensical negative number.
     */
    public function avgQuoteResponseTimeHours(): ?float
    {
        $rows = $this->respondedQuoteResponses()->get(['created_at', 'responded_at']);

        if ($rows->isEmpty()) {
            return null;
        }

        return round(abs($rows->sum(fn ($response) => $response->created_at->diffInMinutes($response->responded_at, false))) / $rows->count() / 60, 1);
    }

    public function quoteResponseRate(): ?float
    {
        $total = $this->quoteResponses()->count();

        return $total > 0 ? round($this->respondedQuoteResponses()->count() / $total * 100) : null;
    }

    /**
     * Only meaningful on a single-organization page (public profile,
     * dashboard) - calling this in a loop over many organizations would
     * N+1 (unlike isTopRated(), it has no eager-loadable equivalent to
     * withRatingStats()).
     */
    public function isFastResponder(): bool
    {
        $avg = $this->avgQuoteResponseTimeHours();

        return $avg !== null
            && $avg <= self::FAST_RESPONDER_MAX_HOURS
            && $this->respondedQuoteResponses()->count() >= self::FAST_RESPONDER_MIN_RESPONSES;
    }

    /**
     * Uses the eager-loaded reviews_avg_rating/reviews_count from
     * withRatingStats() when present (directory/homepage listings), so
     * this is safe to call per-row without N+1 - falls back to a live
     * query only when those aren't loaded (a single organization page).
     */
    public function isTopRated(): bool
    {
        $rating = $this->attributes['reviews_avg_rating'] ?? $this->averageRating();
        $count = $this->attributes['reviews_count'] ?? $this->reviewsCount();

        return $rating !== null && $rating >= self::TOP_RATED_MIN_RATING && $count >= self::TOP_RATED_MIN_REVIEWS;
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
