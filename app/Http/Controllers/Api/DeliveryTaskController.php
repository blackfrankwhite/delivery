<?php

namespace App\Http\Controllers\Api;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignDriverRequest;
use App\Http\Requests\DeliveryTaskRequest;
use App\Http\Requests\DriverNotesRequest;
use App\Http\Requests\ListRequest;
use App\Http\Requests\StatusRequest;
use App\Http\Resources\DeliveryTaskResource;
use App\Models\DeliveryTask;
use App\Services\DeliveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DeliveryTaskController extends Controller
{
    public function __construct(private DeliveryService $service) {}

    public function index(ListRequest $request)
    {
        Gate::authorize('viewAny', DeliveryTask::class);
        $q = DeliveryTask::visibleTo($request->user())->with(['driver', 'customer', 'items']);
        if ($s = $request->input('search')) {
            $q->where(function ($q) use ($s) {
                foreach (['reference_number', 'recipient_name', 'recipient_phone', 'address'] as $f) {
                    $q->orWhere($f, 'like', '%'.$s.'%');
                }
            });
        }
        foreach (['status', 'driver_id', 'customer_id', 'priority'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->input($f));
            }
        }
        if ($request->filled('date')) {
            $q->onDate($request->date);
        }
        if ($request->filled('date_from')) {
            $q->whereDate('date_to', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $q->whereDate('date_from', '<=', $request->date_to);
        }

        return DeliveryTaskResource::collection($q->orderByDesc('delivery_date')->orderBy('sequence')->orderByDesc('id')->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function store(DeliveryTaskRequest $request)
    {
        Gate::authorize('create', DeliveryTask::class);

        return $this->resource($this->service->create($request->validated(), $request->user()))->response()->setStatusCode(201);
    }

    public function show(Request $request, DeliveryTask $deliveryTask)
    {
        Gate::authorize('view', $deliveryTask);

        return $this->resource($deliveryTask);
    }

    public function update(DeliveryTaskRequest $request, DeliveryTask $deliveryTask)
    {
        Gate::authorize('update', $deliveryTask);

        return $this->resource($this->service->update($deliveryTask, $request->validated(), $request->user()));
    }

    public function destroy(Request $request, DeliveryTask $deliveryTask)
    {
        Gate::authorize('delete', $deliveryTask);
        $this->service->delete($deliveryTask, $request->user());

        return response()->noContent();
    }

    public function assign(AssignDriverRequest $request, DeliveryTask $deliveryTask)
    {
        Gate::authorize('assign', $deliveryTask);

        return $this->resource($this->service->assign($deliveryTask, $request->validated('driver_id'), $request->user()));
    }

    public function status(StatusRequest $request, DeliveryTask $deliveryTask)
    {
        Gate::authorize('changeStatus', $deliveryTask);

        return $this->resource($this->service->transition($deliveryTask, DeliveryStatus::from($request->status), $request->user(), $request->input('notes')));
    }

    public function notes(DriverNotesRequest $request, DeliveryTask $deliveryTask)
    {
        Gate::authorize('changeStatus', $deliveryTask);

        return $this->resource($this->service->notes($deliveryTask, $request->user(), $request->input('driver_notes')));
    }

    public function today(ListRequest $request)
    {
        return DeliveryTaskResource::collection(DeliveryTask::where('driver_id', $request->user()->id)->onDate(today())->where(fn ($q) => $q->whereNull('ride_id')->orWhereHas('ride', fn ($ride) => $ride->whereDate('date', today())))->with('items')->orderBy('ride_id')->orderBy('sequence')->orderBy('time_from')->paginate($request->integer('per_page', 20)));
    }

    private function resource(DeliveryTask $task): DeliveryTaskResource
    {
        return new DeliveryTaskResource($task->load(['driver', 'customer', 'items', 'history.user']));
    }
}
