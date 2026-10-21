<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La API no tiene autenticación: solo acepta peticiones desde la propia máquina.
 */
class OnlyLocalhost
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(in_array($request->ip(), ['127.0.0.1', '::1'], true), 403, 'La API solo acepta peticiones desde 127.0.0.1.');

        return $next($request);
    }
}
