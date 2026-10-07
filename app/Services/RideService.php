<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Models\Customer;
use App\Models\DeliveryTask;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RideService
{
    public function __construct(private DeliveryService $deliveries, private RidePlanner $planner) {}

    public function create(array $data, User $actor): Ride
    {
        if (! empty($data['storage_location_id'])) {
            $data['storage_snapshot'] = $this->planner->snapshot($data['storage_location_id']);
        }

        return Ride::create([...$data, 'created_by' => $actor->id]);
    }

    public function delete(Ride $ride, User $actor): void
    {
        DB::transaction(function () use ($ride, $actor) {
            $ride = Ride::whereKey($ride->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('delete', $ride);
            $tasks = $ride->tasks()->lockForUpdate()->get();
            foreach ($tasks as $task) {
                $task->history()->create([
                    'user_id' => $actor->id,
                    'from_status' => $task->status->value,
                    'to_status' => $task->status->value,
                    'notes' => 'Removed from deleted ride #'.$ride->id,
                    'created_at' => now(),
                ]);
                $task->update(['ride_id' => null, 'sequence' => null]);
            }
            $ride->forceFill(['deleted_by' => $actor->id])->save();
            $ride->delete();
        }, 3);
    }

    public function update(Ride $ride, array $data, User $actor): Ride
    {
        return DB::transaction(function () use ($ride, $data, $actor) {
            $ride = Ride::whereKey($ride->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $ride);
            $this->planned($ride);
            if ($ride->tasks()->exists() && ($ride->driver_id !== $data['driver_id'] || $ride->date->toDateString() !== $data['date'])) {
                throw ValidationException::withMessages(['driver_id' => __('Remove the stops before changing the ride driver or date.')]);
            }
            if (array_key_exists('storage_location_id', $data) && $data['storage_location_id'] !== $ride->storage_location_id) {
                $data['storage_snapshot'] = $data['storage_location_id'] ? $this->planner->snapshot($data['storage_location_id']) : null;
                $data['route_plan'] = null;
            }
            $ride->update($data);

            return $ride;
        }, 3);
    }

    public function addTasks(Ride $ride, array $ids, User $actor): Ride
    {
        return DB::transaction(function () use ($ride, $ids, $actor) {
            $ride = Ride::whereKey($ride->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $ride);
            $this->planned($ride);
            $tasks = DeliveryTask::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if ($tasks->count() !== count($ids)) {
                $this->invalid(__('Some orders no longer exist.'));
            }
            $sequence = (int) $ride->tasks()->max('sequence');
            foreach ($ids as $id) {
                $task = $tasks->firstWhere('id', $id);
                if (! $actor->isDispatcher() && $task->driver_id !== $actor->id) {
                    abort(403);
                }
                if ($task->ride_id || ($ride->date->toDateString() < $task->date_from->toDateString() || $ride->date->toDateString() > $task->date_to->toDateString()) || ! in_array($task->status, [DeliveryStatus::Pending, DeliveryStatus::Assigned]) || ($task->driver_id && $task->driver_id !== $ride->driver_id)) {
                    $this->invalid(__('Stops must be pending or assigned, with a date range covering the ride date, without another ride or driver.'));
                }
                $this->deliveries->assignLocked($task, $ride->driver_id, $actor);
                $task->update(['ride_id' => $ride->id, 'sequence' => ++$sequence]);
            }

            return $ride;
        }, 3);
    }

    public function addClient(Ride $ride, int $customerId, User $actor): Ride
    {
        return DB::transaction(function () use ($ride, $customerId, $actor) {
            $ride = Ride::whereKey($ride->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $ride);
            abort_unless($actor->isDispatcher(), 403);
            $this->planned($ride);
            $customer = Customer::whereKey($customerId)->where('active', true)->lockForUpdate()->firstOrFail();
            $address = $customer->addresses()->orderByDesc('is_default')->orderBy('id')->first();
            if (! $address) {
                throw ValidationException::withMessages(['customer_id' => __('This client needs a saved address before being added to a ride.')]);
            }
            if ($ride->tasks()->where('customer_id', $customer->id)->exists()) {
                throw ValidationException::withMessages(['customer_id' => __('This client already has an order on this ride. Add existing orders through Add stops.')]);
            }
            $data = $address->only(['address', 'city', 'latitude', 'longitude', 'entrance', 'floor', 'apartment']);
            $task = $this->deliveries->create([...$data,
                'customer_id' => $customer->id, 'customer_address_id' => $address->id,
                'recipient_name' => $customer->name, 'recipient_phone' => $customer->phone,
                'driver_id' => $ride->driver_id, 'delivery_date' => $ride->date->toDateString(),
                'date_from' => $ride->date->toDateString(), 'date_to' => $ride->date->toDateString(),
                'time_from' => '09:00', 'time_to' => '18:00', 'priority' => 'normal',
                'notes' => $address->instructions, 'items' => [],
            ], $actor);

            return $this->addTasks($ride, [$task->id], $actor);
        }, 3);
    }

    public function removeTask(Ride $ride, DeliveryTask $task, User $actor): Ride
    {
        return DB::transaction(function () use ($ride, $task, $actor) {
            $ride = Ride::whereKey($ride->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $ride);
            $this->planned($ride);
            $task = DeliveryTask::whereKey($task->id)->lockForUpdate()->firstOrFail();
            if ($task->ride_id !== $ride->id) {
                abort(404);
            }
            if (in_array($task->status, [DeliveryStatus::Started, DeliveryStatus::Arrived])) {
                $this->invalid(__('An in-progress stop cannot be removed.'));
            }
            $task->update(['ride_id' => null, 'sequence' => null]);
            foreach ($ride->tasks()->get() as $i => $stop) {
                $stop->update(['sequence' => $i + 1]);
            }

            return $ride;
        }, 3);
    }

    public function reorder(Ride $ride, array $ids, User $actor): Ride
    {
        return DB::transaction(function () use ($ride, $ids, $actor) {
            $ride = Ride::whereKey($ride->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $ride);
            $this->planned($ride);
            $tasks = $ride->tasks()->lockForUpdate()->get();
            if (count($ids) !== $tasks->count() || array_diff($ids, $tasks->modelKeys()) || count(array_unique($ids)) !== count($ids)) {
                $this->invalid(__('Include each stop in this ride exactly once.'));
            }
            foreach ($ids as $i => $id) {
                $tasks->firstWhere('id', $id)->update(['sequence' => $i + 1]);
            }

            return $ride;
        }, 3);
    }

    public function transition(Ride $ride, string $status, User $actor): Ride
    {
        return DB::transaction(function () use ($ride, $status, $actor) {
            $ride = Ride::whereKey($ride->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('changeStatus', $ride);
            $tasks = $ride->tasks()->lockForUpdate()->get();
            if ($status === 'started' && (! $ride->route_plan || ($ride->route_plan['fingerprint'] ?? null) !== $ride->routeFingerprint($tasks))) {
                $this->invalid(__('Plan a current route from storage before starting the ride.'));
            }
            if ($status === 'started' && $ride->status === 'planned' && $tasks->isNotEmpty()) {
                $ride->update(['status' => 'started', 'started_at' => now()]);
            } elseif ($status === 'completed' && $ride->status === 'started' && $tasks->every(fn ($t) => $t->status->terminal())) {
                $ride->update(['status' => 'completed', 'completed_at' => now()]);
            } else {
                throw ValidationException::withMessages(['status' => __('Start a nonempty planned ride; complete it only after all stops are delivered, failed or cancelled.')]);
            }

            return $ride;
        }, 3);
    }

    private function planned(Ride $ride): void
    {
        if ($ride->status !== 'planned') {
            $this->invalid(__('Only planned rides can be edited.'));
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['task_ids' => $message]);
    }
}
