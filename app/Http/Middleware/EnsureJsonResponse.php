<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La API siempre responde JSON: si el cliente no envía "Accept: application/json"
 * se asume, para que los errores usen el contrato de bootstrap/app.php en lugar
 * de HTML o redirecciones.
 */
class EnsureJsonResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api', 'api/*')) {
            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }
}
