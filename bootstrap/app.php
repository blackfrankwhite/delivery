<?php

use App\Http\Middleware\EnsureActive;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(SetLocale::class);
        $middleware->trustProxies(at: '*');
        $middleware->statefulApi();
        $middleware->alias(['active' => EnsureActive::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function ($response) {
            if ($response instanceof JsonResponse && $response->getStatusCode() >= 400) {
                $data = $response->getData(true);
                if (isset($data['message']) && is_string($data['message'])) {
                    $data['message'] = $response->getStatusCode() === 404
                        ? __('Not Found')
                        : __($data['message']);
                    $response->setData($data);
                }
            }

            return $response;
        });
        $exceptions->shouldRenderJsonWhen(fn (Request $r, Throwable $e) => $r->is('api/*') || $r->expectsJson());
    })->create();
