<?php

use App\Http\Middleware\LoginLocalUser;
use App\Http\Middleware\OnlyLocalhost;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // El login automático tiene que ir antes que la comprobación de sesión de Filament
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: LoginLocalUser::class);
        $middleware->api(prepend: [OnlyLocalhost::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Errores de la API siempre en JSON, aunque el cliente no envíe Accept
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
    })->create();
