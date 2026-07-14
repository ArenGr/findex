<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteTemplate extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'destination_country',
        'price_amount',
        'price_currency',
        'offered_hotel_name',
        'flight_details',
        'inclusions',
        'reply_text',
    ];

    protected $casts = [
        'price_amount' => 'decimal:2',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
