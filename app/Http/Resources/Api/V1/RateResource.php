<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CurrencyRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public shape of a rate.
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
            'buy_rate' => (string) $this->resource->buy_rate,
            'sell_rate' => (string) $this->resource->sell_rate,
            'scraped_at' => $this->resource->scraped_at?->toIso8601String(),
        ];
    }
}
