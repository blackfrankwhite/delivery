<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isDispatcher();
    }

    public function view(User $user, Customer $record): bool
    {
        return $user->isDispatcher();
    }

    public function create(User $user): bool
    {
        return $user->isDispatcher();
    }

    public function update(User $user, Customer $record): bool
    {
        return $user->isDispatcher();
    }

    public function delete(User $user, Customer $record): bool
    {
        return $user->isDispatcher();
    }
}
