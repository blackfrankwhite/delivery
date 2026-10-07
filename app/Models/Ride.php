<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ride extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['driver_id', 'date', 'status', 'started_at', 'completed_at', 'notes', 'created_by', 'storage_location_id', 'storage_snapshot', 'route_plan'];

    protected function casts(): array
    {
        return ['storage_snapshot' => 'array', 'route_plan' => 'array', 'date' => 'date:Y-m-d', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function routeFingerprint($tasks): string
    {
        return hash('sha256', json_encode([
            $this->storage_snapshot ? [$this->storage_snapshot['id'], (float) $this->storage_snapshot['latitude'], (float) $this->storage_snapshot['longitude']] : null,
            $tasks->map(fn ($task) => [$task->id, $task->latitude, $task->longitude])->values()->all(),
        ]));
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks()
    {
        return $this->hasMany(DeliveryTask::class)->orderBy('sequence')->orderBy('id');
    }
}
