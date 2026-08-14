<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\RateResource;
use App\Models\CurrencyRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RateController extends ApiController
{
    /** Every current rate for one currency and transaction type. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $currency = $this->currencyFromRequest($request);
        $type = $this->rateTypeFromRequest($request);

        $rates = CurrencyRate::query()
            ->with(['organization', 'currency'])
            ->where('currency_id', $currency->id)
            ->where('rate_type', $type)
            ->whereHas('organization', fn ($query) => $query->active())
            ->get()
            ->sortBy(fn (CurrencyRate $rate) => $rate->organization->name)
            ->values();

        return RateResource::collection($rates)->additional([
            'meta' => [
                'currency' => $currency->code,
                'rate_type' => $type->value,
                'count' => $rates->count(),
            ],
        ]);
    }
}
