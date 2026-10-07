<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryTaskStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = ['delivery_task_id', 'user_id', 'from_status', 'to_status', 'notes', 'created_at'];

    protected $table = 'delivery_task_status_history';

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (self $entry) {
            $entry->created_at ??= now();
        });
    }

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function deliveryTask()
    {
        return $this->belongsTo(DeliveryTask::class);
    }
}
