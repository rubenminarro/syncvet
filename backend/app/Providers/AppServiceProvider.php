<?php

namespace App\Providers;

use App\Support\CurrentClinic;
use App\Support\ExecutionOrigin;
use App\Support\PwnedPasswordsVerifier;
use App\Support\SearchTerm;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Aviso de los orígenes de CORS descartados: como máximo uno por hora.
     */
    public const CORS_WARNING_KEY = 'cors:discarded-origins-warned';

    public const CORS_WARNING_SECONDS = 3600;

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

        // CORS (entrega front-00): config/cors.php descarta de CORS_ALLOWED_ORIGINS lo que no es un
        // origen exacto (todo "*" incluido). Se avisa para que se corrija, como máximo una vez por
        // hora (el arranque corre en cada pedido); la API sigue solo con los orígenes válidos.
        $discarded = config('cors.discarded_origins', []);
        if ($discarded !== [] && self::firstCorsWarningThisHour()) {
            Log::warning('CORS_ALLOWED_ORIGINS trae valores que no son un origen exacto (esquema://host[:puerto]) y se descartaron.', [
                'discarded' => $discarded,
            ]);
        }

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

        // Búsqueda literal de los listados (R-021): "contiene", con "%", "_" y la
        // barra invertida como caracteres comunes (SearchTerm).
        QueryBuilder::macro('whereContains', function (string $column, string $search, string $boolean = 'and') {
            /** @var QueryBuilder $this */
            return $this->whereRaw(
                $this->getGrammar()->wrap($column) . " like ? escape '" . SearchTerm::ESCAPE . "'",
                [SearchTerm::contains($search)],
                $boolean
            );
        });

        QueryBuilder::macro('orWhereContains', function (string $column, string $search) {
            /** @var QueryBuilder $this */
            return $this->whereContains($column, $search, 'or');
        });
    }

    /**
     * Cache::add solo escribe si la clave no existe: true para el primer arranque de la hora.
     * Si el caché no responde (sin la tabla cache, durante un migrate), se avisa igual: el
     * arranque nunca falla por el aviso.
     */
    private static function firstCorsWarningThisHour(): bool
    {
        try {
            return Cache::add(self::CORS_WARNING_KEY, true, self::CORS_WARNING_SECONDS);
        } catch (\Throwable) {
            return true;
        }
    }
}
