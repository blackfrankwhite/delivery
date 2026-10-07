<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryTask extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['reference_number', 'customer_id', 'customer_address_id', 'driver_id', 'ride_id', 'created_by', 'recipient_name', 'recipient_phone', 'address', 'city', 'latitude', 'longitude', 'entrance', 'floor', 'apartment', 'date_from', 'date_to', 'delivery_date', 'time_from', 'time_to', 'status', 'priority', 'notes', 'driver_notes', 'sequence', 'started_at', 'arrived_at', 'completed_at', 'cancelled_at'];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'status' => DeliveryStatus::class, 'date_from' => 'date:Y-m-d', 'date_to' => 'date:Y-m-d', 'delivery_date' => 'date:Y-m-d', 'started_at' => 'datetime', 'arrived_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $task) {
            $task->date_from ??= $task->delivery_date;
            $task->date_to ??= $task->date_from;
            $task->delivery_date ??= $task->date_from;
            $task->time_from ??= '09:00';
            $task->time_to ??= '18:00';
        });
    }

    public function scopeOnDate($query, $date)
    {
        return $query->whereDate('date_from', '<=', $date)->whereDate('date_to', '>=', $date);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerAddress()
    {
        return $this->belongsTo(CustomerAddress::class)->withTrashed();
    }

    public function ride()
    {
        return $this->belongsTo(Ride::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->belongsToMany(Item::class, 'delivery_task_items')->withPivot('quantity', 'notes')->withTimestamps();
    }

    public function history()
    {
        return $this->hasMany(DeliveryTaskStatusHistory::class)->orderBy('id');
    }

    public function scopeVisibleTo($query, User $user)
    {
        return $query->when(! $user->isDispatcher(), fn ($q) => $q->where('driver_id', $user->id));
    }
}
