<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(ListRequest $request)
    {
        Gate::authorize('admin');

        return $this->listing($request, $request->input('role'));
    }

    public function drivers(ListRequest $request)
    {
        Gate::authorize('dispatch');

        return $this->listing($request, 'driver');
    }

    public function operators(ListRequest $request)
    {
        Gate::authorize('dispatch');

        return $this->listing($request, 'operator');
    }

    private function listing(ListRequest $request, ?string $role)
    {
        $q = User::query()->withCount(['deliveries' => fn ($q) => $q->onDate(today())]);
        if ($role) {
            $q->where('role', $role);
        }
        if ($s = $request->input('search')) {
            $q->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")->orWhere('phone', 'like', "%$s%"));
        }
        if ($request->filled('active')) {
            $q->where('active', $request->boolean('active'));
        }

        return UserResource::collection($q->orderBy('name')->paginate($request->integer('per_page', 20)));
    }

    public function store(UserRequest $request)
    {
        Gate::authorize('create', User::class);

        return (new UserResource(User::create($request->validated())))->response()->setStatusCode(201);
    }

    public function show(User $user)
    {
        Gate::authorize('admin');

        return $this->detail($user);
    }

    private function detail(User $user): UserResource
    {
        return new UserResource($user->load(['todayTasks' => fn ($q) => $q->with(['items', 'driver']), 'deliveries' => fn ($q) => $q->with(['items', 'driver'])->latest('delivery_date')->limit(30), 'rides' => fn ($q) => $q->whereDate('date', today())->withCount('tasks')->latest()->limit(10)]));
    }

    public function driver(User $user)
    {
        abort_unless($user->role === Role::Driver, 404);

        Gate::authorize('dispatch');

        return $this->detail($user);
    }

    public function update(UserRequest $request, User $user)
    {
        Gate::authorize('update', $user);
        $data = $request->validated();
        if (empty($data['password'])) {
            unset($data['password']);
        }
        DB::transaction(function () use ($user, $data, $request) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($user->id === $request->user()->id && (! $data['active'] || $data['role'] !== Role::Superadministrator->value)) {
                throw ValidationException::withMessages(['role' => __('You cannot deactivate or demote your own administrator account.')]);
            }
            if ($data['role'] !== $user->role->value && ($user->deliveries()->withTrashed()->exists() || $user->rides()->withTrashed()->exists())) {
                throw ValidationException::withMessages(['role' => __('A driver with delivery history must keep the driver role. Deactivate the account instead.')]);
            }
            $user->update($data);
        });

        return new UserResource($user->fresh());
    }

    public function destroy(User $user)
    {
        Gate::authorize('delete', $user);
        if ($user->id === auth()->id()) {
            throw ValidationException::withMessages(['active' => __('You cannot deactivate yourself.')]);
        }
        $user->update(['active' => false]);

        return response()->noContent();
    }
}
