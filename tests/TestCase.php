<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;

/**
 * Base de todos los tests. RefreshDatabase migra y siembra (DatabaseSeeder:
 * roles, permisos y especies) una sola vez por ejecución, y corre cada test
 * dentro de una transacción que se deshace al terminar.
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Sembrar al migrar (una vez por ejecución, no por test).
     */
    protected bool $seed = true;

    /**
     * Contraseñas que el Have I Been Pwned simulado informa como filtradas.
     *
     * @var list<string>
     */
    protected array $compromisedPasswords = [];

    protected function setUp(): void
    {
        parent::setUp();

        // La caché de Spatie sobrevive entre tests del mismo proceso.
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->fakePwnedPasswords();
    }

    /**
     * Los tests no salen a la red: cualquier pedido HTTP sin simular falla. La regla
     * de contraseñas (uncompromised(), R-009) consulta Have I Been Pwned por
     * k-anonimato: el simulado responde, para los 5 primeros caracteres del SHA-1,
     * los sufijos de $compromisedPasswords.
     */
    private function fakePwnedPasswords(): void
    {
        Http::preventStrayRequests();

        Http::fake([
            'api.pwnedpasswords.com/range/*' => function (Request $request) {
                $prefix = strtoupper(substr($request->url(), -5));
                $lines = [];

                foreach ($this->compromisedPasswords as $password) {
                    $hash = strtoupper(sha1($password));

                    if (str_starts_with($hash, $prefix)) {
                        $lines[] = substr($hash, 5) . ':42';
                    }
                }

                return Http::response(implode("\r\n", $lines));
            },
        ]);
    }
}
