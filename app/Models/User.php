<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'phone', 'active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['role' => Role::class, 'active' => 'boolean', 'password' => 'hashed', 'email_verified_at' => 'datetime'];
    }

    public function isDispatcher(): bool
    {
        return in_array($this->role, [Role::Superadministrator, Role::Operator]);
    }

    public function deliveries()
    {
        return $this->hasMany(DeliveryTask::class, 'driver_id');
    }

    public function todayTasks()
    {
        return $this->hasMany(DeliveryTask::class, 'driver_id')->onDate(today())->where(fn ($q) => $q->whereNull('ride_id')->orWhereHas('ride', fn ($ride) => $ride->whereDate('date', today())))->orderBy('sequence');
    }

    public function rides()
    {
        return $this->hasMany(Ride::class, 'driver_id');
    }
}
