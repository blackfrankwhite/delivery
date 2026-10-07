<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => is_file(public_path('index.html'))
    ? response()->file(public_path('index.html'), ['Cache-Control' => 'no-cache'])
    : response()->json(['application' => 'Dispatch API', 'frontend' => config('app.frontend_url')]));

// Session authentication uses Laravel's web middleware (cookies, session, CSRF).
Route::post('/api/auth/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
Route::post('/api/auth/logout', [AuthController::class, 'logout'])->middleware(['auth:sanctum', 'active']);

// Serve Vue history routes when the production frontend is bundled with Laravel.
Route::get('/{path}', function () {
    abort_unless(is_file(public_path('index.html')), 404);

    return response()->file(public_path('index.html'), ['Cache-Control' => 'no-cache']);
})->where('path', '(?!api(?:/|$)|sanctum(?:/|$)|up(?:/|$)|assets(?:/|$)).*');
