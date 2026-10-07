<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActive
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()?->active) {
            abort(403, __('Your account has been deactivated.'));
        }

        return $next($request);
    }
}
