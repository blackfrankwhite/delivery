<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Ride;
use App\Models\User;

class RidePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ride $ride): bool
    {
        return $user->isDispatcher() || $ride->driver_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isDispatcher() || $user->role === Role::Driver;
    }

    public function update(User $user, Ride $ride): bool
    {
        return $user->isDispatcher() || ($user->role === Role::Driver && $ride->driver_id === $user->id && $ride->status === 'planned');
    }

    public function delete(User $user, Ride $ride): bool
    {
        return $user->role === Role::Superadministrator;
    }

    public function changeStatus(User $user, Ride $ride): bool
    {
        return $this->view($user, $ride);
    }
}
