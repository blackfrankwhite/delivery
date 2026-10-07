<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'email' => $this->email, 'phone' => $this->phone, 'role' => $this->role->value, 'active' => $this->active,
            'today_deliveries' => $this->whenCounted('deliveries'), 'rides' => RideResource::collection($this->whenLoaded('rides')),
            'today_tasks' => DeliveryTaskResource::collection($this->whenLoaded('todayTasks')),
            'deliveries' => DeliveryTaskResource::collection($this->whenLoaded('deliveries')),
        ];
    }
}
