<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryTaskResource extends JsonResource
{
    public function toArray($request): array
    {
        $fields = ['id', 'reference_number', 'customer_id', 'customer_address_id', 'driver_id', 'ride_id', 'created_by', 'recipient_name', 'recipient_phone', 'address', 'city', 'latitude', 'longitude', 'entrance', 'floor', 'apartment', 'time_from', 'time_to', 'priority', 'notes', 'driver_notes', 'sequence'];
        $data = collect($fields)->mapWithKeys(fn ($f) => [$f => $this->{$f}])->all();
        $data['time_from'] = $this->time_from ? substr($this->time_from, 0, 5) : null;
        $data['time_to'] = $this->time_to ? substr($this->time_to, 0, 5) : null;
        $data['date_from'] = $this->date_from->toDateString();
        $data['date_to'] = $this->date_to->toDateString();
        $data['delivery_date'] = $this->delivery_date->toDateString();
        $data['status'] = $this->status->value;
        foreach (['started_at', 'arrived_at', 'completed_at', 'cancelled_at', 'created_at', 'updated_at'] as $f) {
            $data[$f] = $this->{$f}?->toIso8601String();
        }
        $data['driver'] = $this->whenLoaded('driver', fn () => $this->driver ? ['id' => $this->driver->id, 'name' => $this->driver->name, 'phone' => $this->driver->phone] : null);
        $data['customer'] = $this->whenLoaded('customer', fn () => $this->customer ? ['id' => $this->customer->id, 'name' => $this->customer->name] : null);
        $data['items'] = $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => ['item_id' => $item->id, 'name' => $item->name, 'sku' => $item->sku, 'quantity' => $item->pivot->quantity, 'notes' => $item->pivot->notes]));
        $data['history'] = $this->whenLoaded('history', fn () => $this->history->map(fn ($h) => ['id' => $h->id, 'from_status' => $h->from_status, 'to_status' => $h->to_status, 'notes' => $h->notes, 'created_at' => $h->created_at->toIso8601String(), 'user' => ['id' => $h->user->id, 'name' => $h->user->name]]));

        return $data;
    }
}
