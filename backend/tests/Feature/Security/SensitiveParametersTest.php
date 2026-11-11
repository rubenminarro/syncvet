<?php

namespace Tests\Feature\Security;

use App\Support\UserPassword;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use RuntimeException;
use SensitiveParameterValue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Throwable;

/**
 * Segunda protección de la contraseña en las trazas: #[\SensitiveParameter] en las
 * funciones propias que la reciben. Se prueba con zend.exception_ignore_args=Off (la
 * primera protección apagada): getTrace() es lo que usaría un reporte externo.
 */
class SensitiveParametersTest extends TestCase
{
    private string|false $ignoreArgs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ignoreArgs = ini_set('zend.exception_ignore_args', '0');
        config(['demo.password' => 'Demo#Sensible2026']);
    }

    protected function tearDown(): void
    {
        ini_set('zend.exception_ignore_args', (string) $this->ignoreArgs);
        parent::tearDown();
    }

    public function test_user_password_error(): void
    {
        // El validador primero: su provider diferido registraría otro verificador encima.
        app('validator');
        $this->app->instance(UncompromisedVerifier::class, new class implements UncompromisedVerifier
        {
            public function verify($data)
            {
                throw new RuntimeException('falla del verificador');
            }
        });

        $e = $this->catch(fn () => UserPassword::error('Sensible#Clave2026'));

        $this->assertSensitive($e, UserPassword::class, 'error', 0);
    }

    /**
     * Falla dentro de ClinicService::create (sin el rol administrador): el marco de
     * DemoSeeder::clinic sigue activo.
     */
    public function test_demo_seeder_clinic(): void
    {
        $this->withoutRolesSeeder();
        Role::where('name', 'administrador')->delete();

        $e = $this->catch(fn () => $this->runSeeder());

        $this->assertSensitive($e, DemoSeeder::class, 'clinic', 1);
    }

    /**
     * Falla al asignar el rol veterinario: el marco de DemoSeeder::user sigue activo.
     */
    public function test_demo_seeder_user(): void
    {
        $this->withoutRolesSeeder();
        Role::where('name', 'veterinario')->delete();

        $e = $this->catch(fn () => $this->runSeeder());

        $this->assertSensitive($e, DemoSeeder::class, 'user', 2);
    }

    private function assertSensitive(Throwable $e, string $class, string $function, int $position): void
    {
        $frame = collect($e->getTrace())->first(fn (array $frame) => ($frame['class'] ?? null) === $class && $frame['function'] === $function);

        $this->assertNotNull($frame, "{$class}::{$function} no está en la traza.");
        $this->assertArrayHasKey('args', $frame, 'La traza debía conservar los argumentos (ignore_args en Off).');
        $this->assertInstanceOf(SensitiveParameterValue::class, $frame['args'][$position] ?? null);
    }

    private function catch(callable $callback): Throwable
    {
        try {
            $callback();
        } catch (Throwable $e) {
            return $e;
        }

        $this->fail('Debía fallar.');
    }

    /**
     * El DemoSeeder corre RolesAndPermissionsSeeder, que recrearía el rol borrado.
     */
    private function withoutRolesSeeder(): void
    {
        $this->app->instance(RolesAndPermissionsSeeder::class, new class extends RolesAndPermissionsSeeder
        {
            public function run(): void
            {
            }
        });
    }

    private function runSeeder(): void
    {
        $this->app->make(DemoSeeder::class)->setContainer($this->app)->__invoke();
    }
}
