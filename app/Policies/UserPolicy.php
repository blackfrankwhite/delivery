<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Models\User as Account;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isDispatcher();
    }

    public function view(User $user, Account $record): bool
    {
        return $user->isDispatcher();
    }

    public function create(User $user): bool
    {
        return $user->role === Role::Superadministrator;
    }

    public function update(User $user, Account $record): bool
    {
        return $user->role === Role::Superadministrator;
    }

    public function delete(User $user, Account $record): bool
    {
        return $user->role === Role::Superadministrator;
    }
}
