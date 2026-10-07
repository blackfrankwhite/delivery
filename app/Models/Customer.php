<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'phone', 'email', 'notes', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function deliveries()
    {
        return $this->hasMany(DeliveryTask::class);
    }
}
