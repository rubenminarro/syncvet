<?php

namespace Tests\Feature\Access;

use App\Models\Client;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithApiRoutes;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Acceso por rol a cada ruta de la API (FASE 0, entrega 0.5). La matriz se arma
 * con la lista real de rutas (el `permission:` de cada Controller) y con
 * RolesAndPermissionsSeeder::clinicRoles(): una ruta, un permiso o un rol nuevos
 * quedan cubiertos sin tocar este test.
 *
 * - Permitida: la respuesta no es 401, 403, 404 ni 405, ni un error 5xx (un 422
 *   de validación o un 409 de negocio cuentan: el permiso ya pasó).
 * - Prohibida: 403 con `error_code` FORBIDDEN.
 *
 * Se prueba con roles propios: el administrador pasa todo por Gate::before y
 * ocultaría un permiso mal puesto. Las rutas de ROUTES_WITHOUT_PERMISSION
 * (login, logout y me) quedan fuera, con su motivo.
 *
 * Cada pedido corre dentro de una transacción que se revierte: un DELETE permitido
 * no deja sin datos a la ruta siguiente.
 */
class RoleAccessTest extends TestCase
{
    use InteractsWithApiRoutes;
    use InteractsWithAuth;

    private Clinic $clinic;

    /**
     * Valor de cada parámetro de ruta. Una ruta con un parámetro nuevo hace fallar
     * el test hasta que se agregue aquí.
     *
     * @var array<string, string>
     */
    private array $parameters = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = $this->createClinic();
        $client = $this->clientIn($this->clinic);
        $patient = $this->patientOf($client);

        $this->parameters = [
            'client' => (string) $client->id,
            'patient' => (string) $patient->id,
        ];
    }

    /**
     * Cada rol de la clínica llega exactamente a las rutas de sus permisos.
     */
    #[DataProvider('clinicRoles')]
    public function test_each_clinic_role_reaches_exactly_the_routes_of_its_permissions(string $role): void
    {
        $permissions = RolesAndPermissionsSeeder::clinicRoles()[$role]['permissions'];
        $user = $this->userWithRole($this->clinic, $role);

        [$allowed, $forbidden] = $this->assertAccess($user, fn (array $route) => array_intersect($route['permissions'], $permissions) !== []);

        // Que la matriz no sea trivial: el rol tiene rutas permitidas y prohibidas.
        $this->assertGreaterThan(0, $allowed, "{$role} no tiene ninguna ruta permitida.");
        $this->assertGreaterThan(0, $forbidden, "{$role} no tiene ninguna ruta prohibida.");
    }

    public static function clinicRoles(): array
    {
        return array_map(fn (string $role) => [$role], array_combine(
            array_keys(RolesAndPermissionsSeeder::clinicRoles()),
            array_keys(RolesAndPermissionsSeeder::clinicRoles()),
        ));
    }

    /**
     * Sin rol ni permisos: 403 en todo lo que pide permiso.
     */
    public function test_a_user_without_role_is_forbidden_everywhere(): void
    {
        $user = User::factory()->for($this->clinic)->create();

        $this->assertAccess($user, fn () => false);
    }

    /**
     * El administrador pasa todo por Gate::before: se le quitan los permisos al rol
     * para que solo quede Gate::before.
     */
    public function test_the_administrator_passes_every_route_through_gate_before(): void
    {
        Role::findByName('administrador', 'api')->syncPermissions([]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $this->userWithRole($this->clinic, 'administrador');

        $this->assertAccess($user, fn () => true);
    }

    /**
     * Cada ruta exige su permiso y no otro: con un rol propio que solo tiene ese
     * permiso, pasa; con uno que tiene todos los demás, 403.
     */
    public function test_each_route_requires_exactly_its_own_permission(): void
    {
        $failures = [];

        foreach ($this->apiRoutesWithPermission() as $route) {
            $this->assertNotEmpty($route['permissions'], "La ruta {$route['key']} no exige ningún permission:.");

            $only = $this->requestWithPermissions($route, $route['permissions']);
            if (! $this->isAllowed($only)) {
                $failures[] = "{$route['key']}: con solo {$this->list($route)} respondió {$only->status()}";
            }

            $others = array_values(array_diff(RolesAndPermissionsSeeder::PERMISSIONS, $route['permissions']));
            $without = $this->requestWithPermissions($route, $others);
            if (! $this->isForbidden($without)) {
                $failures[] = "{$route['key']}: con todos los permisos menos {$this->list($route)} respondió {$without->status()}";
            }
        }

        $this->assertSame([], $failures);
    }

    /**
     * Sin token, cada ruta protegida responde 401 (antes de mirar el permiso).
     */
    public function test_without_a_token_every_route_answers_401(): void
    {
        $failures = [];

        foreach ($this->apiRoutesWithPermission() as $route) {
            $response = $this->send(null, $route);

            if ($response->status() !== 401 || $response->json('error_code') !== 'UNAUTHENTICATED') {
                $failures[] = "{$route['key']}: respondió {$response->status()}";
            }
        }

        $this->assertSame([], $failures);
    }

    /**
     * Las rutas sin permiso que piden token (logout y me) las usa cualquier usuario
     * autenticado, también sin rol. Login es público.
     */
    public function test_routes_without_permission_are_open_to_any_authenticated_user(): void
    {
        $user = User::factory()->for($this->clinic)->create();
        $routes = $this->apiRoutes();

        foreach (['auth.me', 'auth.logout'] as $key) {
            $this->assertArrayHasKey($key, self::ROUTES_WITHOUT_PERMISSION);
            $response = $this->send($user, $routes[$key]);
            $this->assertSame(200, $response->status(), "{$key} respondió {$response->status()}");
        }
    }

    /**
     * Pide cada ruta protegida con el usuario y compara con lo esperado. Junta todas
     * las diferencias antes de fallar, para ver la matriz completa.
     *
     * @param callable(array): bool $expectedAllowed
     * @return array{0: int, 1: int} rutas permitidas y prohibidas
     */
    private function assertAccess(User $user, callable $expectedAllowed): array
    {
        $failures = [];
        $counts = [0, 0];

        foreach ($this->apiRoutesWithPermission() as $route) {
            $this->assertNotEmpty($route['permissions'], "La ruta {$route['key']} no exige ningún permission:.");

            $allowed = $expectedAllowed($route);
            $counts[$allowed ? 0 : 1]++;
            $response = $this->send($user, $route);

            if ($allowed && ! $this->isAllowed($response)) {
                $failures[] = "{$route['key']} ({$this->list($route)}): debía pasar y respondió {$response->status()}";
            }

            if (! $allowed && ! $this->isForbidden($response)) {
                $failures[] = "{$route['key']} ({$this->list($route)}): debía responder 403 y respondió {$response->status()}";
            }
        }

        $this->assertSame([], $failures);

        return $counts;
    }

    /**
     * Rol propio con esos permisos, creado y revertido junto con el pedido.
     *
     * @param list<string> $permissions
     */
    private function requestWithPermissions(array $route, array $permissions): TestResponse
    {
        return $this->inRolledBackTransaction(function () use ($route, $permissions) {
            $role = Role::create(['name' => 'acceso-prueba', 'guard_name' => 'api']);
            $role->givePermissionTo($permissions);
            $user = $this->userWithRole($this->clinic, 'acceso-prueba');

            return $this->request($user, $route);
        });
    }

    private function send(?User $user, array $route): TestResponse
    {
        return $this->inRolledBackTransaction(fn () => $this->request($user, $route));
    }

    private function request(?User $user, array $route): TestResponse
    {
        $this->app['auth']->forgetGuards();

        if ($user !== null) {
            Sanctum::actingAs($user);
        }

        return $this->json($route['method'], $this->uri($route), []);
    }

    /**
     * Revierte lo que haga el pedido (un DELETE, un rol y un usuario de prueba). La
     * caché de permisos de Spatie no se entera del rollback: se limpia. También el
     * usuario autenticado: si quedara el del pedido anterior (ya revertido), la
     * auditoría del alta siguiente lo usaría como autor y MySQL rechazaría la clave
     * foránea.
     */
    private function inRolledBackTransaction(callable $callback): TestResponse
    {
        $this->app['auth']->forgetGuards();
        DB::beginTransaction();

        try {
            return $callback();
        } finally {
            DB::rollBack();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->app['auth']->forgetGuards();
        }
    }

    private function uri(array $route): string
    {
        return '/' . preg_replace_callback('/\{(\w+)\??\}/', function (array $match) use ($route) {
            $this->assertArrayHasKey($match[1], $this->parameters, "La ruta {$route['key']} usa el parámetro {{$match[1]}}: agregarlo en RoleAccessTest::setUp.");

            return $this->parameters[$match[1]];
        }, $route['uri']);
    }

    private function isAllowed(TestResponse $response): bool
    {
        return ! in_array($response->status(), [401, 403, 404, 405], true) && $response->status() < 500;
    }

    private function isForbidden(TestResponse $response): bool
    {
        return $response->status() === 403 && $response->json('error_code') === 'FORBIDDEN';
    }

    private function list(array $route): string
    {
        return implode('|', $route['permissions']);
    }
}
