<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\DeliveryTask;
use App\Models\User;

class DeliveryTaskPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DeliveryTask $task): bool
    {
        return $user->isDispatcher() || $task->driver_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isDispatcher();
    }

    public function update(User $user, DeliveryTask $task): bool
    {
        return $user->isDispatcher() && ! $task->status->terminal();
    }

    public function delete(User $user, DeliveryTask $task): bool
    {
        return $user->role === Role::Superadministrator;
    }

    public function assign(User $user, DeliveryTask $task): bool
    {
        return $user->isDispatcher();
    }

    public function changeStatus(User $user, DeliveryTask $task): bool
    {
        return $this->view($user, $task);
    }
}
