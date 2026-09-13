<?php

namespace App\Models;

use App\Enums\RateType;
use App\Services\Cache\RateCache;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::saved(function (self $organization) {
            if ($organization->wasChanged(['is_active', 'type', 'slug', 'name'])) {
                RateCache::invalidate();
            }
        });
        static::deleted(fn () => RateCache::invalidate());
    }

    public const TYPES = ['bank', 'exchange', 'insurance', 'tourism', 'other'];

    public const RATES_TYPES = ['bank', 'exchange'];

    public const TOURISM_TYPES = ['tourism'];

    public const INSURANCE_TYPES = ['insurance'];

    protected $fillable = [
        'name',
        'slug',
        'type',
        'website',
        'contact_phone',
        'contact_whatsapp',
        'contact_telegram',
        'contact_instagram',
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

    // Get all sources for this organization.
    public function sources(): HasMany
    {
        return $this->hasMany(OrganizationSource::class);
    }

    // Get all currency rates from this organization.
    public function currencyRates(): HasMany
    {
        return $this->hasMany(CurrencyRate::class);
    }

    // Get all mortgage offers from this organization.
    public function mortgageOffers(): HasMany
    {
        return $this->hasMany(MortgageOffer::class);
    }

    // Get all reviews for this organization.
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

    #[Scope]
    protected function withRatingStats(Builder $query): Builder
    {
        return $query->withCount('reviews')->withAvg('reviews', 'rating');
    }

    // Get all branches for this organization.
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    // Get all report requests for this organization.
    public function reportRequests(): HasMany
    {
        return $this->hasMany(ReportRequest::class);
    }

    // Get all generated reports for this organization.
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    // Destination countries this organization (type: tourism) can quote for.
    public function tourismDestinations(): HasMany
    {
        return $this->hasMany(TourismDestination::class);
    }

    // Quote requests this organization has been asked to reply to.
    public function quoteResponses(): HasMany
    {
        return $this->hasMany(QuoteResponse::class);
    }

    public function quoteTemplates(): HasMany
    {
        return $this->hasMany(QuoteTemplate::class);
    }

    // Auto insurance quotes this organization (type: insurance) has provided.
    public function autoInsuranceQuotes(): HasMany
    {
        return $this->hasMany(AutoInsuranceQuote::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // Scope a query to only include active organizations.
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', 1);
    }

    #[Scope]
    protected function tourismPartnersForRequest(Builder $query, QuoteRequest $request): Builder
    {
        return $query
            ->tourismPartnersForDestination(
                $request->destinations ?: null,
                $request->party_size,
                $request->matching_budget_ceiling,
            )
            ->inRandomOrder()
            ->limit(QuoteRequest::MAX_PARTNERS_PER_REQUEST);
    }

    /**
     * @param  string|array<int, string>|null  $countryCode  One destination, several, or null for
     *                                                       "anywhere" - a traveller open to suggestions names no country, so every
     *                                                       agency serving any active destination is a candidate. Several destinations
     *                                                       match an agency serving *any* of them, not all: an agency that covers one
     *                                                       leg of the trip still has something worth quoting.
     */
    #[Scope]
    protected function tourismPartnersForDestination(Builder $query, string|array|null $countryCode, ?int $partySize = null, ?float $budgetAmd = null): Builder
    {
        return $query->active()
            ->where('type', 'tourism')
            ->where(fn ($query) => $query
                ->whereNotNull('telegram_chat_id')
                ->orWhereHas('users'))
            ->whereHas('tourismDestinations', fn ($query) => $query
                ->when($countryCode !== null, fn ($query) => $query->whereIn('country_code', (array) $countryCode))
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

    #[Scope]
    protected function exchangePartnersForCurrency(Builder $query, int $currencyId, ?string $city = null): Builder
    {
        return $query->active()
            ->where('type', 'exchange')
            ->whereNotNull('telegram_chat_id')
            ->whereHas('currencyRates', fn ($query) => $query
                ->where('currency_id', $currencyId)
                ->where('rate_type', RateType::CASH))
            ->when($city, fn ($query) => $query->whereHas(
                'branches',
                fn ($branches) => $branches->active()->where('city', $city)
            ));
    }

    public function hasRatesPage(): bool
    {
        return in_array($this->type, self::RATES_TYPES, true);
    }

    public function getHasContactInfoAttribute(): bool
    {
        return $this->contact_phone || $this->contact_whatsapp || $this->contact_telegram || $this->contact_instagram;
    }

    public function hasTourismPage(): bool
    {
        return in_array($this->type, self::TOURISM_TYPES, true);
    }

    public function hasInsurancePage(): bool
    {
        return in_array($this->type, self::INSURANCE_TYPES, true);
    }

    // Minimum sample sizes below which a badge would be noise rather than signal (e.g.
    public const FAST_RESPONDER_MAX_HOURS = 6;

    public const FAST_RESPONDER_MIN_RESPONSES = 3;

    public const TOP_RATED_MIN_RATING = 4.5;

    public const TOP_RATED_MIN_REVIEWS = 3;

    public function respondedQuoteResponses(): HasMany
    {
        return $this->quoteResponses()->where('status', QuoteResponse::STATUS_RESPONDED)->whereNotNull('responded_at');
    }

    public function avgQuoteResponseTimeHours(): ?float
    {
        return Cache::remember("org.{$this->id}.avg_response_time_hours", now()->addMinutes(10), function () {
            $rows = $this->respondedQuoteResponses()->get(['created_at', 'responded_at']);

            if ($rows->isEmpty()) {
                return null;
            }

            return round(abs($rows->sum(fn ($response) => $response->created_at->diffInMinutes($response->responded_at, false))) / $rows->count() / 60, 1);
        });
    }

    public function quoteResponseRate(): ?float
    {
        return Cache::remember("org.{$this->id}.quote_response_rate", now()->addMinutes(10), function () {
            $total = $this->quoteResponses()->count();

            return $total > 0 ? round($this->respondedQuoteResponses()->count() / $total * 100) : null;
        });
    }

    public function isFastResponder(): bool
    {
        $avg = $this->avgQuoteResponseTimeHours();

        return $avg !== null
            && $avg <= self::FAST_RESPONDER_MAX_HOURS
            && $this->respondedQuoteResponses()->count() >= self::FAST_RESPONDER_MIN_RESPONSES;
    }

    public function isTopRated(): bool
    {
        // array_key_exists, not ??
        $rating = array_key_exists('reviews_avg_rating', $this->attributes)
            ? $this->attributes['reviews_avg_rating']
            : $this->averageRating();

        $count = array_key_exists('reviews_count', $this->attributes)
            ? $this->attributes['reviews_count']
            : $this->reviewsCount();

        return $rating !== null && $rating >= self::TOP_RATED_MIN_RATING && $count >= self::TOP_RATED_MIN_REVIEWS;
    }

    public function getDescriptionAttribute(): ?string
    {
        $locales = array_unique([
            app()->getLocale(),
            config('localization.default'),
            ...array_keys(config('localization.available')),
        ]);

        foreach ($locales as $locale) {
            if (! empty($this->attributes["description_{$locale}"] ?? null)) {
                return $this->attributes["description_{$locale}"];
            }
        }

        return null;
    }
}
