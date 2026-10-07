<?php

namespace App\Policies;

use App\Models\CustomerAddress;
use App\Models\User;

class CustomerAddressPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isDispatcher();
    }

    public function view(User $user, CustomerAddress $record): bool
    {
        return $user->isDispatcher();
    }

    public function create(User $user): bool
    {
        return $user->isDispatcher();
    }

    public function update(User $user, CustomerAddress $record): bool
    {
        return $user->isDispatcher();
    }

    public function delete(User $user, CustomerAddress $record): bool
    {
        return $user->isDispatcher();
    }
}
