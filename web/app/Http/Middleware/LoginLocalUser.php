<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * En local y preproducción inicia sesión automáticamente con el usuario creado
 * por LocalUserSeeder, para que Filament no pida nada. En el resto de entornos
 * no hace nada y Filament manda a la página del enlace de acceso.
 */
class LoginLocalUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check() && app()->environment(config('fuji.auto_login_environments'))) {
            $user = User::where('email', config('fuji.local_user.email'))->first()
                ?? throw new RuntimeException('No existe el usuario local. Ejecuta: php artisan db:seed');

            Auth::login($user);
        }

        return $next($request);
    }
}
