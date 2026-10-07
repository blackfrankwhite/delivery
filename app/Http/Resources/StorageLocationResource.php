<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StorageLocationResource extends JsonResource
{
    public function toArray($request): array
    {
        return collect(['id', 'name', 'address', 'city', 'latitude', 'longitude', 'instructions', 'active'])->mapWithKeys(fn ($field) => [$field => $this->{$field}])->all();
    }
}
