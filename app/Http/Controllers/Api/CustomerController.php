<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Http\Requests\ListRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CustomerController extends Controller
{
    public function index(ListRequest $request)
    {
        Gate::authorize('viewAny', Customer::class);
        $q = Customer::with('addresses');
        if ($s = $request->input('search')) {
            $q->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")->orWhere('email', 'like', "%$s%"));
        }
        if ($request->filled('active')) {
            $q->where('active', $request->boolean('active'));
        }

        return CustomerResource::collection($q->orderBy('name')->paginate($request->integer('per_page', 20)));
    }

    public function store(CustomerRequest $request)
    {
        Gate::authorize('create', Customer::class);

        $customer = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $address = $data['default_address'];
            unset($data['default_address']);
            $customer = Customer::create($data);
            $customer->addresses()->create([...$address, 'is_default' => true]);

            return $customer->load('addresses');
        });

        return (new CustomerResource($customer))->response()->setStatusCode(201);
    }

    public function show(Customer $customer)
    {
        Gate::authorize('view', $customer);

        return new CustomerResource($customer->load(['addresses', 'deliveries' => fn ($q) => $q->with(['items', 'driver'])->latest()->limit(20)]));
    }

    public function update(CustomerRequest $request, Customer $customer)
    {
        Gate::authorize('update', $customer);
        DB::transaction(function () use ($request, $customer) {
            Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $data = $request->validated();
            $address = $data['default_address'] ?? null;
            unset($data['default_address']);
            $customer->update($data);
            if ($address) {
                $default = $customer->addresses()->orderByDesc('is_default')->orderBy('id')->first();
                $customer->addresses()->update(['is_default' => false]);
                if ($default) {
                    $default->update([...$address, 'is_default' => true]);
                } else {
                    $customer->addresses()->create([...$address, 'is_default' => true]);
                }
            }
        });

        return new CustomerResource($customer->load('addresses'));
    }

    public function destroy(Customer $customer)
    {
        Gate::authorize('delete', $customer);
        $customer->update(['active' => false]);

        return response()->noContent();
    }
}
