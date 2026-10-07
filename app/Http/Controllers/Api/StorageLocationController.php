<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Http\Requests\StorageLocationRequest;
use App\Http\Resources\StorageLocationResource;
use App\Models\StorageLocation;
use Illuminate\Support\Facades\Gate;

class StorageLocationController extends Controller
{
    public function rideOptions()
    {
        return StorageLocationResource::collection(StorageLocation::where('active', true)->whereNotNull('latitude')->whereNotNull('longitude')->orderBy('name')->get());
    }

    public function index(ListRequest $request)
    {
        Gate::authorize('admin');
        $query = StorageLocation::query();
        if ($search = $request->input('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('address', 'like', '%'.$search.'%')->orWhere('city', 'like', '%'.$search.'%'));
        }
        if ($request->filled('active')) {
            $query->where('active', $request->boolean('active'));
        }

        return StorageLocationResource::collection($query->orderBy('name')->paginate($request->integer('per_page', 20)));
    }

    public function store(StorageLocationRequest $request)
    {
        return (new StorageLocationResource(StorageLocation::create($request->validated())))->response()->setStatusCode(201);
    }

    public function show(StorageLocation $storageLocation)
    {
        Gate::authorize('admin');

        return new StorageLocationResource($storageLocation);
    }

    public function update(StorageLocationRequest $request, StorageLocation $storageLocation)
    {
        $storageLocation->update($request->validated());

        return new StorageLocationResource($storageLocation);
    }

    public function destroy(StorageLocation $storageLocation)
    {
        Gate::authorize('admin');
        $storageLocation->delete();

        return response()->noContent();
    }
}
