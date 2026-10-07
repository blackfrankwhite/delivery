<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ItemRequest;
use App\Http\Requests\ListRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use Illuminate\Support\Facades\Gate;

class ItemController extends Controller
{
    public function index(ListRequest $request)
    {
        Gate::authorize('viewAny', Item::class);
        $q = Item::query();
        if ($s = $request->input('search')) {
            $q->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('sku', 'like', "%$s%"));
        }
        if ($request->filled('active')) {
            $q->where('active', $request->boolean('active'));
        }

        return ItemResource::collection($q->orderBy('name')->paginate($request->integer('per_page', 20)));
    }

    public function store(ItemRequest $request)
    {
        Gate::authorize('create', Item::class);

        return (new ItemResource(Item::create($request->validated())))->response()->setStatusCode(201);
    }

    public function show(Item $item)
    {
        Gate::authorize('view', $item);

        return new ItemResource($item);
    }

    public function update(ItemRequest $request, Item $item)
    {
        Gate::authorize('update', $item);
        $item->update($request->validated());

        return new ItemResource($item);
    }

    public function destroy(Item $item)
    {
        Gate::authorize('delete', $item);
        $item->update(['active' => false]);

        return response()->noContent();
    }
}
