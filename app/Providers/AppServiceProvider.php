<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::define('driver', fn (User $user) => $user->role === Role::Driver);
        Gate::define('dispatch', fn (User $user) => $user->isDispatcher());
        Gate::define('admin', fn (User $user) => $user->role === Role::Superadministrator);
    }
}
