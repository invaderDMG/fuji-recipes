<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * App local de un solo usuario: inicia sesión automáticamente con el usuario
 * creado por LocalUserSeeder, para que Filament no pida credenciales.
 */
class LoginLocalUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            $user = User::where('email', config('fuji.local_user.email'))->first()
                ?? throw new RuntimeException('No existe el usuario local. Ejecuta: php artisan db:seed');

            Auth::login($user);
        }

        return $next($request);
    }
}
