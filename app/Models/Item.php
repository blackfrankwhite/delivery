<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'sku', 'description', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function deliveries()
    {
        return $this->belongsToMany(DeliveryTask::class, 'delivery_task_items')->withPivot('quantity', 'notes')->withTimestamps();
    }
}
