<?php

namespace App\Providers;

use App\Support\CurrentClinic;
use App\Support\ExecutionOrigin;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Clínica en contexto: se descarta al terminar cada pedido o job de la cola (D1).
        $this->app->scoped(CurrentClinic::class);
    }

    public function boot(): void
    {
        // Job en curso, para el log de ClinicContextMissingException (R-005).
        ExecutionOrigin::listen();

        // El administrador pasa todos los permisos. Un nombre de permiso mal escrito
        // no se nota con él: probar siempre con un rol propio.
        Gate::before(function ($user, $ability) {
            return $user->hasRole('administrador') ? true : null;
        });
    }
}
