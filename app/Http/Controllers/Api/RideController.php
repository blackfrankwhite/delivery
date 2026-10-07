<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Http\Requests\RidePlanRequest;
use App\Http\Requests\RideReorderRequest;
use App\Http\Requests\RideRequest;
use App\Http\Requests\RideStatusRequest;
use App\Http\Requests\RideTasksRequest;
use App\Http\Resources\RideResource;
use App\Models\DeliveryTask;
use App\Models\Ride;
use App\Services\RidePlanner;
use App\Services\RideService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RideController extends Controller
{
    public function __construct(private RideService $service, private RidePlanner $planner) {}

    public function index(ListRequest $request)
    {
        Gate::authorize('viewAny', Ride::class);
        $q = Ride::with('driver')->withCount('tasks')->when(! $request->user()->isDispatcher(), fn ($q) => $q->where('driver_id', $request->user()->id));
        foreach (['driver_id', 'status', 'date'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->input($f));
            }
        }

        return RideResource::collection($q->orderByDesc('date')->orderByDesc('id')->paginate($request->integer('per_page', 20)));
    }

    public function store(RideRequest $request)
    {
        Gate::authorize('create', Ride::class);

        return $this->resource($this->service->create($request->validated(), $request->user()))->response()->setStatusCode(201);
    }

    public function show(Ride $ride)
    {
        Gate::authorize('view', $ride);

        return $this->resource($ride);
    }

    public function update(RideRequest $request, Ride $ride)
    {
        Gate::authorize('update', $ride);

        return $this->resource($this->service->update($ride, $request->validated(), $request->user()));
    }

    public function destroy(Request $request, Ride $ride)
    {
        Gate::authorize('delete', $ride);
        $this->service->delete($ride, $request->user());

        return response()->noContent();
    }

    public function addTasks(RideTasksRequest $request, Ride $ride)
    {
        Gate::authorize('update', $ride);

        return $this->resource($this->service->addTasks($ride, $request->task_ids, $request->user()));
    }

    public function addClient(Request $request, Ride $ride)
    {
        Gate::authorize('update', $ride);
        abort_unless($request->user()->isDispatcher(), 403);
        $data = $request->validate(['customer_id' => ['required', 'integer', 'exists:customers,id']]);

        return $this->resource($this->service->addClient($ride, $data['customer_id'], $request->user()));
    }

    public function removeTask(Request $request, Ride $ride, DeliveryTask $deliveryTask)
    {
        Gate::authorize('update', $ride);

        return $this->resource($this->service->removeTask($ride, $deliveryTask, $request->user()));
    }

    public function reorder(RideReorderRequest $request, Ride $ride)
    {
        Gate::authorize('update', $ride);

        return $this->resource($this->service->reorder($ride, $request->task_ids, $request->user()));
    }

    public function plan(RidePlanRequest $request, Ride $ride)
    {
        return $this->resource($this->planner->plan($ride, $request->user(), $request->input('storage_location_id'), $request->boolean('optimize')));
    }

    public function status(RideStatusRequest $request, Ride $ride)
    {
        Gate::authorize('changeStatus', $ride);

        return $this->resource($this->service->transition($ride, $request->status, $request->user()));
    }

    private function resource(Ride $ride): RideResource
    {
        return new RideResource($ride->load(['driver', 'tasks.items'])->loadCount('tasks'));
    }
}
