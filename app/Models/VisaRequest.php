<?php

namespace App\Models;

use App\Enums\VisaRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Intl\Countries;

class VisaRequest extends Model
{
    public const MAX_APPLICANTS = 20;

    public const MAX_PARTNERS_PER_REQUEST = 25;

    public const DAYS_OPEN = 14;

    protected $fillable = [
        'user_id',
        'guest_name',
        'guest_email',
        'locale',
        'destination_country',
        'travel_from',
        'travel_to',
        'applicants',
        'status',
        'expires_at',
    ];

    protected $attributes = [
        'status' => 'submitted',
        'applicants' => 1,
    ];

    protected $casts = [
        'travel_from' => 'date',
        'travel_to' => 'date',
        'applicants' => 'integer',
        'status' => VisaRequestStatus::class,
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getRequesterNameAttribute(): ?string
    {
        return $this->user?->name ?? $this->guest_name;
    }

    public function getRequesterEmailAttribute(): ?string
    {
        return $this->user?->email ?? $this->guest_email;
    }

    public function getIsOpenAttribute(): bool
    {
        return $this->currentStatus()->isOpen();
    }

    // The one place anything should ask what state a request is in.
    public function currentStatus(): VisaRequestStatus
    {
        if ($this->status === VisaRequestStatus::CLOSED) {
            return VisaRequestStatus::CLOSED;
        }

        return $this->expires_at?->isFuture()
            ? $this->status
            : VisaRequestStatus::EXPIRED;
    }

    public function markOffersReceived(): void
    {
        if ($this->status === VisaRequestStatus::SUBMITTED) {
            $this->forceFill(['status' => VisaRequestStatus::OFFERS_RECEIVED])->save();
        }
    }

    public function close(): void
    {
        $this->forceFill(['status' => VisaRequestStatus::CLOSED])->save();
    }

    public function getDestinationLabelAttribute(): string
    {
        $key = 'destinations.'.$this->destination_country;

        return Lang::has($key)
            ? __($key)
            : (Countries::exists($this->destination_country)
                ? Countries::getName($this->destination_country, app()->getLocale())
                : $this->destination_country);
    }

    public function signedResultsUrl(): string
    {
        return $this->signedUrlFor('visa.show');
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function signedUrlFor(string $routeName, array $parameters = []): string
    {
        return URL::signedRoute($routeName, array_merge([
            'locale' => $this->locale,
            'visaRequest' => $this->id,
        ], $parameters), $this->expires_at);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(VisaResponse::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('expires_at', '>', now())
            ->where('status', '!=', VisaRequestStatus::CLOSED->value);
    }

    // How many agencies were asked, and how many have answered.
    public function scopeWithProgressCounts($query)
    {
        return $query
            ->withCount([
                'responses as contacted_count',
                'responses as responded_count' => fn ($query) => $query->where('status', VisaResponse::STATUS_RESPONDED),
            ]);
    }
}
