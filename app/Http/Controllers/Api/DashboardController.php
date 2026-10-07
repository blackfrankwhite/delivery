<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryTaskResource;
use App\Models\DeliveryTask;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __invoke()
    {
        Gate::authorize('dispatch');
        $q = DeliveryTask::onDate(today());
        $counts = (clone $q)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');

        return response()->json(['data' => [
            'date' => today()->toDateString(), 'total' => $counts->sum(), 'pending' => (int) ($counts['pending'] ?? 0), 'assigned' => (int) ($counts['assigned'] ?? 0),
            'in_progress' => (int) ($counts['started'] ?? 0) + (int) ($counts['arrived'] ?? 0), 'delivered' => (int) ($counts['delivered'] ?? 0), 'failed' => (int) ($counts['failed'] ?? 0), 'cancelled' => (int) ($counts['cancelled'] ?? 0),
            'active_drivers' => User::where('role', 'driver')->where('active', true)->count(),
            'deliveries' => DeliveryTaskResource::collection((clone $q)->with(['driver', 'items'])->orderBy('time_from')->orderBy('id')->limit(12)->get()),
        ]]);
    }
}
