<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// A key to the public API.
class ApiKey extends Model
{
    protected $fillable = ['user_id', 'organization_id', 'name', 'prefix', 'token_hash', 'plan'];

    protected $casts = [
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * Issues a key and returns it exactly once.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(array $attributes = []): array
    {
        $token = 'fx_'.Str::random(40);

        $key = static::create([
            ...$attributes,
            'prefix' => substr($token, 0, 11),
            'token_hash' => hash('sha256', $token),
        ]);

        return [$key, $token];
    }

    public static function findByToken(string $token): ?self
    {
        return static::whereNull('revoked_at')
            ->where('token_hash', hash('sha256', $token))
            ->first();
    }

    // The commercial terms behind this key.
    public function limits(): array
    {
        $plans = config('api.plans');

        return $plans[$this->plan] ?? $plans[config('api.anonymous_plan')];
    }

    public function recordUse(): void
    {
        ApiKeyUsage::query()->upsert(
            [['api_key_id' => $this->id, 'day' => now()->toDateString(), 'requests' => 1,
                'created_at' => now(), 'updated_at' => now()]],
            ['api_key_id', 'day'],
            ['requests' => DB::raw('requests + 1'), 'updated_at' => now()],
        );

        if ($this->last_used_at === null || $this->last_used_at->isBefore(now()->startOfDay())) {
            $this->forceFill(['last_used_at' => now()])->saveQuietly();
        }
    }

    public function usages(): HasMany
    {
        return $this->hasMany(ApiKeyUsage::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
