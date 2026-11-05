<?php

namespace App\Http\Middleware;

use App\Exceptions\ClinicException;
use App\Support\CurrentClinic;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija la clínica del usuario autenticado durante el pedido (D1). Va después de
 * auth:sanctum y ANTES de SubstituteBindings (bootstrap/app.php): así el route
 * model binding ya filtra por la clínica y un id de otra clínica da 404.
 */
class SetCurrentClinic
{
    public function handle(Request $request, Closure $next): Response
    {
        $clinic = $request->user()?->clinic;

        if ($clinic === null) {
            // Sin usuario no se llega aquí (auth:sanctum responde 401 antes).
            abort(401);
        }

        if (! $clinic->isActive()) {
            throw new ClinicException('La clínica no está disponible.', 403, 'CLINIC_NOT_AVAILABLE');
        }

        // run() restaura el contexto anterior al terminar, también si el pedido falla.
        return CurrentClinic::run($clinic, fn () => $next($request));
    }
}
