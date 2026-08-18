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
    /**
     * Destination countries a request can be filed for, and the only values
     * a tourism organization can register as "served" (see TourismDestination).
     * Kept as a plain const list rather than a DB table or enum, matching how
     * Organization::TYPES-style lists already work elsewhere in this app.
     */
    public const DESTINATIONS = ['AE', 'EG', 'GE', 'GR', 'TH', 'CY', 'IT', 'FR', 'ES'];

    /**
     * Whether flights belong in the quote at all. "Flexible" is the useful
     * middle ground the other two can't express: the traveler will take
     * whichever works out cheaper, and wants the agency to advise.
     */
    public const FLIGHT_INCLUDED = 'included';

    public const FLIGHT_NOT_NEEDED = 'not_needed';

    public const FLIGHT_FLEXIBLE = 'flexible';

    public const FLIGHT_PREFERENCES = [self::FLIGHT_INCLUDED, self::FLIGHT_NOT_NEEDED, self::FLIGHT_FLEXIBLE];

    /**
     * Minimum hotel class, not an exact one - "4" means four stars or
     * better, which is how a traveler picking it actually means it and how
     * the offer-side hotel_stars is compared against it.
     */
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

    /**
     * What the traveler cares about most, for an agency deciding which way
     * to lean when it can't have everything at once. Kept to a short fixed
     * list (and capped at MAX_PRIORITIES below) so it stays a genuine
     * signal - "everything matters" tells an agency nothing.
     */
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

    /**
     * How firm the stated dates are. Null means exact - the traveller is
     * travelling on those days and no others, which is the default and so
     * isn't stored as a value of its own.
     */
    public const DATES_PLUS_3 = 'plus_3';

    public const DATES_PLUS_7 = 'plus_7';

    public const DATES_MONTH = 'month';

    public const DATE_FLEXIBILITY_OPTIONS = [self::DATES_PLUS_3, self::DATES_PLUS_7, self::DATES_MONTH];

    /**
     * How many destinations one request may name. A cap because past a
     * handful this stops being a trip and starts being a survey - and
     * because every extra destination widens the set of agencies the
     * request fans out to.
     */
    public const MAX_DESTINATIONS = 5;

    public const MAX_CHILDREN = 10;

    /** Ages an agency can price a child fare against. */
    public const MAX_CHILD_AGE = 17;

    /**
     * The budget bands the request form offers, in AMD. A band rather than
     * a free figure because that's genuinely how far ahead a traveler has
     * thought at this point - and because the number an agency needs is the
     * ceiling it's quoting against, which a band states exactly as well as
     * a slider did.
     *
     * A null on either side means "no bound stated": the first band has no
     * floor, the last no ceiling, and 'flexible' neither. Both ends are
     * stored as-is in budget_min_amd/budget_max_amd, which is what the
     * partner matching already reads (see
     * Organization::tourismPartnersForDestination()).
     */
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

    /**
     * Mirrors the column defaults, so a request built in memory answers
     * currentStatus() and the preference accessors the same way one read
     * back from the database does - without this, anything that inspects a
     * model between create() and a refresh() sees nulls the database would
     * never have stored.
     */
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

    /**
     * The account name if filed while signed in, otherwise the guest's own name.
     */
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

    /**
     * The one place anything should ask what state a request is in. The
     * stored status only ever holds a decided state; expiry is folded in
     * here from expires_at rather than written to the column, so the answer
     * can't drift out of date between the clock passing and something
     * getting round to updating the row (see QuoteRequestStatus).
     */
    public function currentStatus(): QuoteRequestStatus
    {
        if ($this->status === QuoteRequestStatus::CLOSED) {
            return QuoteRequestStatus::CLOSED;
        }

        return $this->expires_at->isFuture() ? $this->status : QuoteRequestStatus::EXPIRED;
    }

    /**
     * Called when an agency submits an offer. Deliberately not a status
     * setter: the only transition out of SUBMITTED that an incoming offer
     * can cause is this one, and a request the traveler has already closed
     * must not be dragged back open by a late reply.
     */
    public function markOffersReceived(): void
    {
        if ($this->status === QuoteRequestStatus::SUBMITTED) {
            $this->forceFill(['status' => QuoteRequestStatus::OFFERS_RECEIVED])->save();
        }
    }

    /**
     * The traveler ending the request early - they've picked an agency, or
     * they're no longer travelling. Offers already received stay visible
     * (see the offers list); this only stops new ones arriving.
     */
    public function close(): void
    {
        $this->forceFill(['status' => QuoteRequestStatus::CLOSED])->save();
    }

    /**
     * The traveler's stated dates as a night count - the figure that
     * belongs in a trip brief, since "Sep 12-19" and "7 nights" answer
     * different questions and an agency prices against the second.
     */
    public function getNightsAttribute(): int
    {
        return (int) $this->check_in->diffInDays($this->check_out);
    }

    public function getHasFlexibleDatesAttribute(): bool
    {
        return $this->date_flexibility !== null;
    }

    /**
     * Every destination named, as ISO codes. Falls back to the single
     * destination_country for rows written before a request could name more
     * than one, so callers never have to check which era a row is from.
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
     * Keeps destination_country pointing at the first of the list. It stays
     * the one-destination answer that the Telegram brief, the emails and
     * the destination alerts all read, so it must never disagree with the
     * list it's drawn from - doing it here means no caller can forget.
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
     * The destinations translation files only cover the curated set an
     * agency can register as serving, so anything outside it falls back to
     * Symfony's own translated country name rather than a bare code.
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

    /**
     * "2 adults, 1 child" style summary, with the children's ages when
     * they were given - an agency prices a 2-year-old and a 15-year-old
     * very differently, so the ages belong wherever the party does.
     */
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
     * Anything no longer in PRIORITIES is dropped rather than rendered as a
     * raw key, so retiring an option can't leave old requests showing
     * "flexible_cancellation" to an agency.
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

    /**
     * Matched against a partner's opt-in min_lead_party_size (see
     * Organization::tourismPartnersForDestination()).
     */
    public function getPartySizeAttribute(): int
    {
        return $this->adults + $this->children;
    }

    /**
     * "Closes in 3 days" style countdown for surfacing urgency on the
     * customer-facing request pages - null once the request has closed
     * (those pages show the fixed closed date instead, see is_open).
     */
    public function getClosesInAttribute(): ?string
    {
        return $this->is_open ? $this->expires_at->diffForHumans(['parts' => 1]) : null;
    }

    /**
     * The single figure matched against a partner's opt-in
     * min_lead_budget_amd (see Organization::tourismPartnersForDestination()) -
     * the stated max is the most a partner could hope to capture, so a
     * partner's minimum is checked against it rather than the min (a
     * request with only a min stated falls back to that, since it's the
     * only figure available).
     */
    public function getBudgetForFilteringAttribute(): ?float
    {
        $value = $this->budget_max_amd ?? $this->budget_min_amd;

        return $value !== null ? (float) $value : null;
    }

    /**
     * A guest has no account to log back into, so this signed link (emailed
     * on submission and on every partner reply) is their only way back to
     * the results page - it stays valid exactly as long as the request
     * itself stays open to new replies.
     */
    public function signedResultsUrl(): string
    {
        return $this->signedUrlFor('tourism.show');
    }

    /**
     * The offers list. A separate signed link rather than a fragment of the
     * status one: Laravel's signature covers the exact route, so a guest
     * arriving on the status page can't simply walk to a sibling page - the
     * status page mints each link it offers through here.
     */
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

    /**
     * Every priced option across every agency that answered - the flat list
     * the offers page, the comparison and the "how many offers" counts all
     * work from. Going through the responses relation one at a time is the
     * N+1 this exists to avoid.
     */
    public function offers(): HasManyThrough
    {
        return $this->hasManyThrough(QuoteSuggestion::class, QuoteResponse::class, 'quote_request_id', 'quote_response_id');
    }

    /**
     * The four numbers every request-facing screen reports, counted in the
     * query rather than by walking the loaded relations - so the requests
     * list can show them for twenty requests in one round trip instead of
     * twenty. "Reviewing" means opened but not yet answered (see
     * QuoteResponse::markViewed); an agency that has replied is counted as
     * responded, not as still reviewing.
     */
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

    /**
     * Requests agencies can still reply to. The status check matters as
     * much as the clock: a traveler who closed a request early has stopped
     * wanting offers, even though its expires_at is still in the future.
     */
    public function scopeOpen($query)
    {
        return $query->where('expires_at', '>', now())
            ->where('status', '!=', QuoteRequestStatus::CLOSED->value);
    }
}
