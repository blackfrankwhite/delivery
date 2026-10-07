<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StorageLocation extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'address', 'city', 'latitude', 'longitude', 'instructions', 'active'];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'active' => 'boolean'];
    }
}
