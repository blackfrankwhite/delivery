<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Models\Customer;
use App\Models\DeliveryTask;
use App\Models\Ride;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    public function create(array $data, User $actor): DeliveryTask
    {
        return DB::transaction(function () use ($data, $actor) {
            $data = $this->clientSnapshot($data);
            $items = $data['items'];
            unset($data['items']);
            $date = now()->toDateString();
            DB::table('delivery_reference_sequences')->insertOrIgnore(['date' => $date, 'value' => 0]);
            $counter = DB::table('delivery_reference_sequences')->where('date', $date)->lockForUpdate()->first();
            DB::table('delivery_reference_sequences')->where('date', $date)->increment('value');
            $data['reference_number'] = 'DEL-'.str_replace('-', '', $date).'-'.str_pad($counter->value + 1, 4, '0', STR_PAD_LEFT);
            $data['created_by'] = $actor->id;
            $data['status'] = empty($data['driver_id']) ? DeliveryStatus::Pending : DeliveryStatus::Assigned;
            $task = DeliveryTask::create($data);
            $this->syncItems($task, $items);
            $task->history()->create(['user_id' => $actor->id, 'to_status' => $task->status->value, 'notes' => 'Order created', 'created_at' => $task->created_at]);

            return $task;
        }, 3);
    }

    public function update(DeliveryTask $task, array $data, User $actor): DeliveryTask
    {
        return DB::transaction(function () use ($task, $data, $actor) {
            $task = DeliveryTask::whereKey($task->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $task);
            if ($task->ride_id && (($task->ride->date->toDateString() < $data['date_from'] || $task->ride->date->toDateString() > $data['date_to']) || $task->driver_id !== ($data['driver_id'] ?? null))) {
                throw ValidationException::withMessages(['date_from' => __('Remove this order from its ride before changing its date or driver.')]);
            }
            $data = $this->clientSnapshot($data);
            $driver = $data['driver_id'] ?? null;
            if ($task->driver_id !== $driver) {
                $this->assignLocked($task, $driver, $actor);
            }
            $items = $data['items'];
            unset($data['items'],$data['driver_id']);
            $task->update($data);
            $this->syncItems($task, $items);

            return $task;
        }, 3);
    }

    private function clientSnapshot(array $data): array
    {
        if (empty($data['customer_id']) || empty($data['customer_address_id'])) {
            throw ValidationException::withMessages(['customer_id' => __('Choose a client and a saved address for this order.')]);
        }
        $client = Customer::whereKey($data['customer_id'])->lockForUpdate()->firstOrFail();
        $address = $client->addresses()->whereKey($data['customer_address_id'])->lockForUpdate()->first();
        if (! $address) {
            throw ValidationException::withMessages(['customer_address_id' => __('Choose a saved address belonging to this client.')]);
        }

        return [...$data, ...$address->only(['address', 'city', 'latitude', 'longitude', 'entrance', 'floor', 'apartment']),
            'recipient_name' => $client->name, 'recipient_phone' => $client->phone];
    }

    public function delete(DeliveryTask $task, User $actor): void
    {
        DB::transaction(function () use ($task, $actor) {
            if ($task->ride_id) {
                Ride::whereKey($task->ride_id)->lockForUpdate()->firstOrFail();
            }
            $task = DeliveryTask::whereKey($task->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('delete', $task);
            $task->history()->create(['user_id' => $actor->id, 'from_status' => $task->status->value, 'to_status' => $task->status->value, 'notes' => 'Order deleted', 'created_at' => now()]);
            $task->delete();
        }, 3);
    }

    private function syncItems(DeliveryTask $task, array $items): void
    {
        $task->items()->sync(collect($items)->mapWithKeys(fn ($row) => [$row['item_id'] => ['quantity' => $row['quantity'], 'notes' => $row['notes'] ?? null]])->all());
    }

    public function assign(DeliveryTask $task, ?int $driverId, User $actor): DeliveryTask
    {
        return DB::transaction(function () use ($task, $driverId, $actor) {
            $task = DeliveryTask::whereKey($task->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('assign', $task);
            $this->assignLocked($task, $driverId, $actor);

            return $task;
        }, 3);
    }

    // Called with a locked task inside an existing transaction (also by RideService).
    public function assignLocked(DeliveryTask $task, ?int $driverId, User $actor): void
    {
        if ($task->driver_id === $driverId) {
            return;
        }
        if (! in_array($task->status, [DeliveryStatus::Pending, DeliveryStatus::Assigned])) {
            throw ValidationException::withMessages(['driver_id' => __('Only pending or assigned orders can be reassigned.')]);
        }
        if ($task->ride_id && $task->driver_id !== $driverId) {
            throw ValidationException::withMessages(['driver_id' => __('Remove the order from its ride before reassigning it.')]);
        }
        if ($driverId && ! User::whereKey($driverId)->where('role', 'driver')->where('active', true)->exists()) {
            throw ValidationException::withMessages(['driver_id' => __('Select an active driver.')]);
        }
        $from = $task->status;
        $task->driver_id = $driverId;
        $task->status = $driverId ? DeliveryStatus::Assigned : DeliveryStatus::Pending;
        $task->save();
        if ($from !== $task->status) {
            $this->history($task, $from, $actor, $driverId ? 'Driver assigned' : 'Driver unassigned');
        }
    }

    public function transition(DeliveryTask $task, DeliveryStatus $to, User $actor, ?string $notes = null): DeliveryTask
    {
        return DB::transaction(function () use ($task, $to, $actor, $notes) {
            $task = DeliveryTask::whereKey($task->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('changeStatus', $task);
            $allowed = match ($task->status) {
                DeliveryStatus::Assigned => [DeliveryStatus::Started, DeliveryStatus::Failed],
                DeliveryStatus::Started => [DeliveryStatus::Arrived, DeliveryStatus::Failed],
                DeliveryStatus::Arrived => [DeliveryStatus::Delivered, DeliveryStatus::Failed],
                default => [],
            };
            if ($actor->isDispatcher() && ! $task->status->terminal()) {
                $allowed[] = DeliveryStatus::Cancelled;
            }
            if (! in_array($to, $allowed)) {
                throw ValidationException::withMessages(['status' => __('This order status transition is not allowed.')]);
            }
            if ($to === DeliveryStatus::Failed && ! trim($notes ?? '')) {
                throw ValidationException::withMessages(['notes' => __('Please explain why the order failed.')]);
            }
            $from = $task->status;
            $changedAt = now();
            $task->status = $to;
            $timestamp = match ($to) {
                DeliveryStatus::Started => 'started_at', DeliveryStatus::Arrived => 'arrived_at', DeliveryStatus::Delivered => 'completed_at', DeliveryStatus::Cancelled => 'cancelled_at', default => null
            };
            if ($timestamp) {
                $task->{$timestamp} = $changedAt;
            }
            if (! $actor->isDispatcher() && $notes !== null) {
                $task->driver_notes = $notes;
            }
            $task->save();
            $this->history($task, $from, $actor, $notes, $changedAt);

            return $task;
        }, 3);
    }

    public function notes(DeliveryTask $task, User $actor, ?string $notes): DeliveryTask
    {
        return DB::transaction(function () use ($task, $actor, $notes) {
            $task = DeliveryTask::whereKey($task->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('changeStatus', $task);
            $task->update(['driver_notes' => $notes]);

            return $task;
        });
    }

    private function history(DeliveryTask $task, DeliveryStatus $from, User $actor, ?string $notes, ?CarbonInterface $changedAt = null): void
    {
        $task->history()->create(['user_id' => $actor->id, 'from_status' => $from->value, 'to_status' => $task->status->value, 'notes' => $notes, 'created_at' => $changedAt ?? $task->updated_at]);
    }
}
