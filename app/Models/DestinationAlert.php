<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DestinationAlert extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'destination_country',
        'locale',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
