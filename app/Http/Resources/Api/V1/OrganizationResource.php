<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property Organization $resource */
class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->resource->slug,
            'name' => $this->resource->name,
            'type' => $this->resource->type,
            'website' => $this->resource->website,
        ];
    }
}
