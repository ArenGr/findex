<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\CurrencyResource;
use App\Models\Currency;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CurrencyController extends ApiController
{
    public function index(): AnonymousResourceCollection
    {
        return CurrencyResource::collection(
            Currency::where('is_active', true)->orderBy('sort_order')->get()
        );
    }
}
