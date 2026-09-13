<?php

namespace App\Models;

use App\Enums\QuoteRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Intl\Countries;

class QuoteRequest extends Model
{
    public const DESTINATIONS = ['AE', 'EG', 'GE', 'GR', 'TH', 'CY', 'IT', 'FR', 'ES'];

    // Whether flights belong in the quote at all.
    public const FLIGHT_INCLUDED = 'included';

    public const FLIGHT_NOT_NEEDED = 'not_needed';

    public const FLIGHT_FLEXIBLE = 'flexible';

    public const FLIGHT_PREFERENCES = [self::FLIGHT_INCLUDED, self::FLIGHT_NOT_NEEDED, self::FLIGHT_FLEXIBLE];

    public const HOTEL_ANY = 'any';

    public const HOTEL_PREFERENCES = [self::HOTEL_ANY, '3', '4', '5'];

    public const MEAL_ANY = 'any';

    public const MEAL_BREAKFAST = 'breakfast';

    public const MEAL_HALF_BOARD = 'half_board';

    public const MEAL_FULL_BOARD = 'full_board';

    public const MEAL_ALL_INCLUSIVE = 'all_inclusive';

    public const MEAL_PREFERENCES = [
        self::MEAL_ANY,
        self::MEAL_BREAKFAST,
        self::MEAL_HALF_BOARD,
        self::MEAL_FULL_BOARD,
        self::MEAL_ALL_INCLUSIVE,
    ];

    public const PRIORITIES = [
        'lowest_price',
        'best_value',
        'better_hotel',
        'direct_flight',
        'good_location',
        'all_inclusive',
        'family_friendly',
    ];

    public const MAX_PRIORITIES = 3;

    // How firm the stated dates are.
    public const DATES_PLUS_3 = 'plus_3';

    public const DATES_PLUS_7 = 'plus_7';

    public const DATES_MONTH = 'month';

    public const DATE_FLEXIBILITY_OPTIONS = [self::DATES_PLUS_3, self::DATES_PLUS_7, self::DATES_MONTH];

    // How many destinations one request may name.
    public const MAX_DESTINATIONS = 5;

    // How many agencies one request may be sent to.
    public const MAX_PARTNERS_PER_REQUEST = 25;

    public const MAX_CHILDREN = 10;

    /** Ages an agency can price a child fare against. */
    public const MAX_CHILD_AGE = 17;

    // The budget bands the request form offers, in AMD.
    public const BUDGET_BANDS = [
        'under_500k' => ['min' => null, 'max' => 500000],
        '500k_1m' => ['min' => 500000, 'max' => 1000000],
        '1m_2m' => ['min' => 1000000, 'max' => 2000000],
        'over_2m' => ['min' => 2000000, 'max' => null],
        'flexible' => ['min' => null, 'max' => null],
    ];

    protected $fillable = [
        'user_id',
        'guest_name',
        'guest_email',
        'locale',
        'departure_location',
        'destination_country',
        'destination_countries',
        'open_to_suggestions',
        'hotel_name',
        'check_in',
        'check_out',
        'date_flexibility',
        'adults',
        'children',
        'child_ages',
        'flight_preference',
        'hotel_preference',
        'meal_preference',
        'priorities',
        'insurance',
        'notes',
        'status',
        'expires_at',
        'review_prompted_at',
        'budget_min_amd',
        'budget_max_amd',
        'budget_currency',
    ];

    protected $attributes = [
        'status' => QuoteRequestStatus::SUBMITTED->value,
        'flight_preference' => self::FLIGHT_FLEXIBLE,
        'hotel_preference' => self::HOTEL_ANY,
        'meal_preference' => self::MEAL_ANY,
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'adults' => 'integer',
        'children' => 'integer',
        'destination_countries' => 'array',
        'child_ages' => 'array',
        'open_to_suggestions' => 'boolean',
        'priorities' => 'array',
        'insurance' => 'boolean',
        'status' => QuoteRequestStatus::class,
        'expires_at' => 'datetime',
        'review_prompted_at' => 'datetime',
        'budget_min_amd' => 'decimal:2',
        'budget_max_amd' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // The account name if filed while signed in, otherwise the guest's own name.
    public function getRequesterNameAttribute(): ?string
    {
        return $this->user->name ?? $this->guest_name;
    }

    public function getRequesterEmailAttribute(): ?string
    {
        return $this->user->email ?? $this->guest_email;
    }

    public function getIsOpenAttribute(): bool
    {
        return $this->currentStatus()->isOpen();
    }

    // The one place anything should ask what state a request is in.
    public function currentStatus(): QuoteRequestStatus
    {
        if ($this->status === QuoteRequestStatus::CLOSED) {
            return QuoteRequestStatus::CLOSED;
        }

        return $this->expires_at->isFuture() ? $this->status : QuoteRequestStatus::EXPIRED;
    }

    // Called when an agency submits an offer.
    public function markOffersReceived(): void
    {
        if ($this->status === QuoteRequestStatus::SUBMITTED) {
            $this->forceFill(['status' => QuoteRequestStatus::OFFERS_RECEIVED])->save();
        }
    }

    public function close(): void
    {
        $this->forceFill(['status' => QuoteRequestStatus::CLOSED])->save();
    }

    public function getNightsAttribute(): int
    {
        return (int) $this->check_in->diffInDays($this->check_out);
    }

    public function getHasFlexibleDatesAttribute(): bool
    {
        return $this->date_flexibility !== null;
    }

    /**
     * Every destination named, as ISO codes.
     *
     * @return array<int, string>
     */
    public function getDestinationsAttribute(): array
    {
        if (! empty($this->destination_countries)) {
            return array_values($this->destination_countries);
        }

        return $this->destination_country ? [$this->destination_country] : [];
    }

    /**
     * Keeps destination_country pointing at the first of the list.
     *
     * @param  array<int, string>  $countryCodes
     */
    public function setDestinations(array $countryCodes): void
    {
        $countryCodes = array_values(array_unique($countryCodes));

        $this->destination_countries = $countryCodes;
        $this->destination_country = $countryCodes[0] ?? null;
    }

    /**
     * Translated destination names, for anywhere showing the whole list.
     *
     * @return array<int, string>
     */
    public function getDestinationLabelsAttribute(): array
    {
        return collect($this->destinations)
            ->map(fn ($code) => Lang::has('destinations.'.$code)
                ? __('destinations.'.$code)
                : (Countries::exists($code) ? Countries::getName($code, app()->getLocale()) : $code))
            ->all();
    }

    public function getTravellersLabelAttribute(): string
    {
        $parts = [trans_choice('tourism.brief.adults', $this->adults, ['count' => $this->adults])];

        if ($this->children > 0) {
            $children = trans_choice('tourism.brief.children', $this->children, ['count' => $this->children]);

            if (! empty($this->child_ages)) {
                $children .= ' ('.implode(', ', $this->child_ages).')';
            }

            $parts[] = $children;
        }

        return implode(', ', $parts);
    }

    /**
     * Translated priority labels, in the order the traveler picked them.
     *
     * @return array<int, string>
     */
    public function getPriorityLabelsAttribute(): array
    {
        return collect($this->priorities ?? [])
            ->filter(fn ($priority) => in_array($priority, self::PRIORITIES, true))
            ->map(fn ($priority) => __('tourism.priorities.'.$priority))
            ->values()
            ->all();
    }

    public function getPartySizeAttribute(): int
    {
        return $this->adults + $this->children;
    }

    public function getClosesInAttribute(): ?string
    {
        return $this->is_open ? $this->expires_at->diffForHumans(['parts' => 1]) : null;
    }

    public function getBudgetForFilteringAttribute(): ?float
    {
        $value = $this->budget_max_amd ?? $this->budget_min_amd;

        return $value !== null ? (float) $value : null;
    }

    public function getMatchingBudgetCeilingAttribute(): ?float
    {
        if ($this->budget_max_amd !== null) {
            return (float) $this->budget_max_amd;
        }

        return $this->budget_min_amd !== null ? PHP_FLOAT_MAX : null;
    }

    public function signedResultsUrl(): string
    {
        return $this->signedUrlFor('tourism.show');
    }

    // The offers list.
    public function signedOffersUrl(): string
    {
        return $this->signedUrlFor('tourism.offers');
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function signedUrlFor(string $routeName, array $parameters = []): string
    {
        return URL::signedRoute($routeName, array_merge([
            'locale' => $this->locale,
            'quoteRequest' => $this->id,
        ], $parameters), $this->expires_at);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(QuoteResponse::class);
    }

    public function offers(): HasManyThrough
    {
        return $this->hasManyThrough(QuoteSuggestion::class, QuoteResponse::class, 'quote_request_id', 'quote_response_id');
    }

    public function scopeWithProgressCounts($query)
    {
        return $query->withCount([
            'responses as contacted_count',
            'responses as reviewing_count' => fn ($query) => $query
                ->where('status', QuoteResponse::STATUS_PENDING)
                ->whereNotNull('viewed_at'),
            'responses as responded_count' => fn ($query) => $query
                ->where('status', QuoteResponse::STATUS_RESPONDED),
            'offers as offers_count',
        ]);
    }

    // Requests agencies can still reply to.
    public function scopeOpen($query)
    {
        return $query->where('expires_at', '>', now())
            ->where('status', '!=', QuoteRequestStatus::CLOSED->value);
    }
}
