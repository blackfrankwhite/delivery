<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'phone' => $this->phone, 'email' => $this->email, 'notes' => $this->notes, 'active' => $this->active,
            'addresses' => CustomerAddressResource::collection($this->whenLoaded('addresses')),
            'deliveries' => DeliveryTaskResource::collection($this->whenLoaded('deliveries'))];
    }
}
