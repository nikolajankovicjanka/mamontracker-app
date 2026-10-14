<?php

use App\Http\Middleware\ConfigureSessionCookie;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(ConfigureSessionCookie::class);

        $middleware->statefulApi();

        $middleware->web(append: [
            ResolveTenant::class,
        ]);

        $middleware->api(prepend: [
            ResolveTenant::class,
        ]);

        $middleware->alias([
            'tenant.feature' => \App\Http\Middleware\EnsureTenantHasFeature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
