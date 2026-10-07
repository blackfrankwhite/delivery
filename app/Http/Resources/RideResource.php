<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RideResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id, 'driver_id' => $this->driver_id, 'date' => $this->date->toDateString(), 'status' => $this->status, 'notes' => $this->notes, 'created_by' => $this->created_by,
            'storage_location_id' => $this->storage_location_id, 'storage' => $this->storage_snapshot,
            'route_plan' => $this->whenLoaded('tasks', fn () => $this->route_plan && ($this->route_plan['fingerprint'] ?? null) === $this->routeFingerprint($this->tasks) ? $this->route_plan : null),
            'route_plan_stale' => $this->whenLoaded('tasks', fn () => $this->route_plan && ($this->route_plan['fingerprint'] ?? null) !== $this->routeFingerprint($this->tasks)),
            'started_at' => $this->started_at?->toIso8601String(), 'completed_at' => $this->completed_at?->toIso8601String(),
            'driver' => $this->whenLoaded('driver', fn () => ['id' => $this->driver->id, 'name' => $this->driver->name, 'phone' => $this->driver->phone]),
            'tasks_count' => $this->whenCounted('tasks'), 'tasks' => DeliveryTaskResource::collection($this->whenLoaded('tasks')),
        ];
    }
}
