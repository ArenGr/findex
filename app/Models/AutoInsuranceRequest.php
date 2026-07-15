<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

class AutoInsuranceRequest extends Model
{
    public const OWNER_TYPES = ['individual', 'legal_entity'];

    public const CONTRACT_TERMS = [3, 6, 12];

    protected $fillable = [
        'user_id',
        'guest_name',
        'guest_email',
        'locale',
        'vehicle_plate',
        'owner_type',
        'owner_id_number',
        'contract_term_months',
        'engine_power_hp',
        'driver_experience_years',
        'accident_free_years',
    ];

    protected $casts = [
        'contract_term_months' => 'integer',
        'engine_power_hp' => 'integer',
        'driver_experience_years' => 'integer',
        'accident_free_years' => 'integer',
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

    /**
     * A guest has no account to log back into, so this signed link is their
     * only way back to the results page - unlike a tourism quote request,
     * there's no natural expiry (quotes are generated once, synchronously,
     * and don't change afterward), so this is signed without a time limit.
     */
    public function signedResultsUrl(): string
    {
        return URL::signedRoute('insurance.auto.show', [
            'locale' => $this->locale,
            'autoInsuranceRequest' => $this->id,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(AutoInsuranceQuote::class);
    }
}
