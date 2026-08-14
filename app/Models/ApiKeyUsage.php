<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row per key per day - counts, not a request log. */
class ApiKeyUsage extends Model
{
    protected $fillable = ['api_key_id', 'day', 'requests'];

    protected $casts = ['day' => 'date'];

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }
}
