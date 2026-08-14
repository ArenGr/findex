<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CurrencyRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public shape of a rate.
 *
 * Deliberately not $this->resource->toArray(): the database is ours to change
 * and this is not. Every field here is a promise, so each one is written out on
 * purpose - a column added tomorrow does not silently join the contract, and a
 * column renamed does not silently break it.
 *
 * @property CurrencyRate $resource
 */
class RateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'organization' => [
                'slug' => $this->resource->organization->slug,
                'name' => $this->resource->organization->name,
                'type' => $this->resource->organization->type,
            ],
            'currency' => $this->resource->currency->code,
            'rate_type' => $this->resource->rate_type->value,
            // Strings, not floats: these are exact decimals, and a consumer
            // parsing 367.00 as a double and printing 366.99999 is a support
            // ticket we would rather not create.
            'buy_rate' => (string) $this->resource->buy_rate,
            'sell_rate' => (string) $this->resource->sell_rate,
            'scraped_at' => $this->resource->scraped_at?->toIso8601String(),
        ];
    }
}
