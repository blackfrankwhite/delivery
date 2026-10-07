<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerAddress extends Model
{
    use HasFactory;

    protected $fillable = ['customer_id', 'label', 'address', 'city', 'latitude', 'longitude', 'entrance', 'floor', 'apartment', 'instructions', 'is_default'];

    use SoftDeletes;

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'is_default' => 'boolean'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
