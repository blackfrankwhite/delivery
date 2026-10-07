<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddressSearchRequest;
use App\Services\AddressSearchService;
use Illuminate\Http\JsonResponse;

class AddressSearchController extends Controller
{
    public function __invoke(AddressSearchRequest $request, AddressSearchService $service): JsonResponse
    {
        return response()->json(['data' => $service->search($request->validated('q'), $request->validated('city'))]);
    }
}
