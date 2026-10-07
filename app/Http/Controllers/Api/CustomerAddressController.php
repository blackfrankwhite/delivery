<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerAddressRequest;
use App\Http\Resources\CustomerAddressResource;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CustomerAddressController extends Controller
{
    public function index(Customer $customer)
    {
        Gate::authorize('view', $customer);

        return CustomerAddressResource::collection($customer->addresses()->orderByDesc('is_default')->get());
    }

    public function store(CustomerAddressRequest $request, Customer $customer)
    {
        Gate::authorize('update', $customer);
        $address = DB::transaction(function () use ($request, $customer) {
            Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            if ($customer->addresses()->exists()) {
                throw ValidationException::withMessages(['address' => __('Each client can have one address. Edit the existing address instead.')]);
            }

            return $customer->addresses()->create([...$request->validated(), 'is_default' => true]);
        });

        return (new CustomerAddressResource($address))->response()->setStatusCode(201);
    }

    public function update(CustomerAddressRequest $request, Customer $customer, CustomerAddress $address)
    {
        Gate::authorize('update', $customer);
        abort_unless($address->customer_id === $customer->id, 404);
        DB::transaction(function () use ($request, $customer, $address) {
            Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            if ($request->boolean('is_default')) {
                $customer->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
            }
            $address->update([...$request->validated(), 'is_default' => true]);
        });

        return new CustomerAddressResource($address);
    }

    public function destroy(Customer $customer, CustomerAddress $address)
    {
        Gate::authorize('update', $customer);
        abort_unless($address->customer_id === $customer->id, 404);
        DB::transaction(function () use ($customer, $address) {
            Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            if ($customer->addresses()->count() <= 1) {
                throw ValidationException::withMessages(['address' => __('Each client must retain at least one saved address.')]);
            }
            $address->delete();
            if (! $customer->addresses()->where('is_default', true)->exists()) {
                $customer->addresses()->orderBy('id')->first()->update(['is_default' => true]);
            }
        });

        return response()->noContent();
    }
}
