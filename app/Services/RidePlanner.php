<?php

namespace App\Services;

use App\Models\Ride;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RidePlanner
{
    public function __construct(private RoutingService $routing) {}

    public function snapshot(int $id): array
    {
        $storage = StorageLocation::whereKey($id)->where('active', true)->firstOrFail();
        $point = $this->point($storage);
        if (! $point) {
            $this->invalid('storage_location_id', 'Select a storage location with a map pin.');
        }

        return ['id' => $storage->id, 'name' => $storage->name, 'address' => $storage->address, 'city' => $storage->city, 'instructions' => $storage->instructions, ...$point];
    }

    public function plan(Ride $ride, User $actor, ?int $storageId, bool $optimize): Ride
    {
        Gate::forUser($actor)->authorize('view', $ride);
        if ($ride->status === 'completed' || ($ride->status !== 'planned' && ($optimize || $storageId))) {
            $this->invalid('route', 'Stop order and storage can only be changed before the ride starts.');
        }
        $ride->load('tasks');
        $original = $ride->routeFingerprint($ride->tasks);
        $start = $storageId ? $this->snapshot($storageId) : $ride->storage_snapshot;
        if (! $start || ! $this->point((object) $start)) {
            $this->invalid('storage_location_id', 'Select a storage location with a map pin.');
        }
        if ($ride->tasks->isEmpty()) {
            $this->invalid('task_ids', 'Add orders before planning the ride.');
        }
        if ($ride->tasks->count() > config('routing.max_stops')) {
            $this->invalid('task_ids', 'Route planning supports up to 50 stops per ride.');
        }
        $points = [$start];
        foreach ($ride->tasks as $task) {
            if (! $point = $this->point($task)) {
                $this->invalid('task_ids', 'Every order needs a map pin before planning the ride.');
            }
            $points[] = $point;
        }
        $indexes = $optimize ? $this->routing->optimize($points) : range(1, count($points) - 1);
        $orderedPoints = [$start, ...array_map(fn ($i) => $points[$i], $indexes)];
        $ids = array_map(fn ($i) => $ride->tasks[$i - 1]->id, $indexes);
        $directions = $this->routing->directions($orderedPoints);

        // Network requests finish before taking database locks. Reject stale results.
        return DB::transaction(function () use ($ride, $actor, $storageId, $start, $original, $ids, $directions, $optimize) {
            $current = Ride::whereKey($ride->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('view', $current);
            if ($current->status === 'completed' || ($current->status !== 'planned' && ($optimize || $storageId))) {
                $this->invalid('route', 'Stop order and storage can only be changed before the ride starts.');
            }
            $tasks = $current->tasks()->lockForUpdate()->get();
            if ($current->routeFingerprint($tasks) !== $original) {
                $this->invalid('route', 'The ride changed during planning. Refresh and calculate it again.');
            }
            foreach ($ids as $index => $id) {
                $tasks->firstWhere('id', $id)->update(['sequence' => $index + 1]);
            }
            $current->storage_location_id = $start['id'];
            $current->storage_snapshot = $start;
            $ordered = collect($ids)->map(fn ($id) => $tasks->firstWhere('id', $id));
            $current->route_plan = [
                ...$directions, 'task_ids' => $ids,
                'fingerprint' => $current->routeFingerprint($ordered),
                'calculated_at' => now()->toIso8601String(), 'calculated_by' => $actor->id,
                'optimized' => $optimize,
            ];
            $current->save();

            return $current;
        }, 3);
    }

    private function point(object $location): ?array
    {
        $lat = $location->latitude;
        $lon = $location->longitude;
        if ($lat === null || $lon === null || ! is_numeric($lat) || ! is_numeric($lon) || ! is_finite((float) $lat) || ! is_finite((float) $lon) || abs($lat) > 90 || abs($lon) > 180) {
            return null;
        }

        return ['latitude' => (float) $lat, 'longitude' => (float) $lon];
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => __($message)]);
    }
}
