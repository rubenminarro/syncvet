<?php

namespace App\Providers;

use App\Support\CurrentClinic;
use App\Support\ExecutionOrigin;
use App\Support\PwnedPasswordsVerifier;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Ninguna traza lleva los argumentos de las funciones (R-010): una contraseña,
        // un token o un dato personal no llegan al log ni a un reporte externo, sin
        // depender de zend.exception_ignore_args del php.ini (en desarrollo suele estar
        // en Off). Es lo primero, para cubrir el resto del arranque.
        ini_set('zend.exception_ignore_args', '1');

        // Clínica en contexto: se descarta al terminar cada pedido o job de la cola (D1).
        $this->app->scoped(CurrentClinic::class);

        // Verificador propio de uncompromised() (R-010): 3 segundos de espera y, si Have
        // I Been Pwned no responde, un aviso sin traza. extend() y no singleton(): el
        // ValidationServiceProvider es diferido y, al cargarse, registraría el de Laravel
        // encima de este.
        $this->app->extend(
            UncompromisedVerifier::class,
            fn ($verifier, $app) => new PwnedPasswordsVerifier($app->make(HttpFactory::class))
        );
    }

    public function boot(): void
    {
        // Job en curso, para el log de ClinicContextMissingException (R-005).
        ExecutionOrigin::listen();

        // Contraseña de los usuarios (R-009): al menos 12 caracteres, con mayúsculas,
        // minúsculas, números y símbolos, y que no aparezca en filtraciones conocidas
        // (Have I Been Pwned, por k-anonimato: solo viajan 5 caracteres del SHA-1). Si
        // el servicio no responde, se acepta (PwnedPasswordsVerifier, R-010). La usan
        // clinic:create y el DemoSeeder; en la FASE 2, el ABM de usuarios.
        Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised());

        // El administrador pasa todos los permisos. Un nombre de permiso mal escrito
        // no se nota con él: probar siempre con un rol propio.
        Gate::before(function ($user, $ability) {
            return $user->hasRole('administrador') ? true : null;
        });
    }
}
