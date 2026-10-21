<?php

use App\Http\Middleware\LoginLocalUser;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // El login automático tiene que ir antes que la comprobación de sesión de Filament
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: LoginLocalUser::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
