<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerAddressResource extends JsonResource
{
    public function toArray($request): array
    {
        return collect(['id', 'customer_id', 'label', 'address', 'city', 'latitude', 'longitude', 'entrance', 'floor', 'apartment', 'instructions', 'is_default'])->mapWithKeys(fn ($f) => [$f => $this->{$f}])->all();
    }
}
