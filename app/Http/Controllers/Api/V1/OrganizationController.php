<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\OrganizationResource;
use App\Http\Resources\Api\V1\RateResource;
use App\Models\Organization;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrganizationController extends ApiController
{
    public function index(): AnonymousResourceCollection
    {
        return OrganizationResource::collection(
            Organization::active()
                ->whereIn('type', Organization::RATES_TYPES)
                ->orderBy('name')
                ->get()
        );
    }

    /** Everything one organization currently quotes. */
    public function rates(string $organization): AnonymousResourceCollection
    {
        $organization = Organization::active()->where('slug', $organization)->firstOrFail();

        return RateResource::collection(
            $organization->currencyRates()->with(['organization', 'currency'])->get()
        )->additional(['meta' => ['organization' => $organization->slug]]);
    }
}
