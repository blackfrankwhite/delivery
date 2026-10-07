<?php

namespace App\Policies;

use App\Models\Item;
use App\Models\User;

class ItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isDispatcher();
    }

    public function view(User $user, Item $record): bool
    {
        return $user->isDispatcher();
    }

    public function create(User $user): bool
    {
        return $user->isDispatcher();
    }

    public function update(User $user, Item $record): bool
    {
        return $user->isDispatcher();
    }

    public function delete(User $user, Item $record): bool
    {
        return $user->isDispatcher();
    }
}
