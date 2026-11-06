<?php

namespace Tests\Feature\Access;

use App\Models\Clinic;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithApiRoutes;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Acceso por rol a cada ruta de la API (FASE 0, entrega 0.5). La matriz se arma
 * con la lista real de rutas (el `permission:` de cada Controller) y con
 * RolesAndPermissionsSeeder::clinicRoles(): una ruta, un método, un permiso o un
 * rol nuevos quedan cubiertos sin tocar este test. Cada método de una ruta se pide
 * por separado (PUT y PATCH, y cualquier ruta con varios); HEAD es el mismo GET.
 *
 * - Permitida: la respuesta no es 401, 403, 404 ni 405, ni un error 5xx (un 422
 *   de validación o un 409 de negocio cuentan: el permiso ya pasó).
 * - Prohibida: 403 con `error_code` FORBIDDEN.
 *
 * Se prueba con roles propios: el administrador pasa todo por Gate::before y
 * ocultaría un permiso mal puesto. Las rutas de ROUTES_WITHOUT_PERMISSION
 * (login, logout y me) quedan fuera de la matriz: se prueban aparte.
 *
 * Cada pedido corre dentro de una transacción que se revierte: un DELETE permitido
 * no deja sin datos al pedido siguiente. Los roles y usuarios de prueba se crean
 * antes, fuera de esas transacciones, para no reconstruir la caché de permisos de
 * Spatie en cada pedido.
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
        // Propias: una global respondería 403 CATALOG_GLOBAL_NOT_EDITABLE al editarla.
        [$species, $breed] = $this->ownSpeciesWithBreed($this->clinic, 'Hurón');

        $this->parameters = [
            'client' => (string) $client->id,
            'patient' => (string) $patient->id,
            'species' => (string) $species,
            'breed' => (string) $breed,
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
        $routes = $this->apiRoutesWithPermission();
        $users = [];

        // Un usuario por conjunto de permisos, creados todos antes de los pedidos.
        foreach ($routes as $route) {
            $this->assertNotEmpty($route['permissions'], "La ruta {$route['key']} no exige ningún permission:.");
            $list = $this->list($route);

            $users["solo {$list}"] ??= $this->userWithPermissions($route['permissions']);
            $users["sin {$list}"] ??= $this->userWithPermissions(
                array_values(array_diff(RolesAndPermissionsSeeder::PERMISSIONS, $route['permissions'])),
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $failures = [];

        foreach ($routes as $route) {
            $list = $this->list($route);

            foreach ($route['methods'] as $method) {
                $only = $this->send($users["solo {$list}"], $route, $method);
                if (! $this->isAllowed($only)) {
                    $failures[] = "{$method} {$route['uri']}: con solo {$list} respondió {$only->status()}";
                }

                $without = $this->send($users["sin {$list}"], $route, $method);
                if (! $this->isForbidden($without)) {
                    $failures[] = "{$method} {$route['uri']}: con todos los permisos menos {$list} respondió {$without->status()}";
                }
            }
        }

        $this->assertSame([], $failures);
    }

    /**
     * Sin token, toda ruta responde 401 (antes de mirar el permiso), salvo las
     * públicas (PUBLIC_ROUTES: el login), que no lo piden.
     */
    public function test_without_a_token_every_route_but_the_public_ones_answers_401(): void
    {
        $failures = [];
        $checked = 0;

        foreach ($this->apiRoutes() as $key => $route) {
            $public = in_array($key, self::PUBLIC_ROUTES, true);

            foreach ($route['methods'] as $method) {
                $checked++;
                $response = $this->send(null, $route, $method);
                $unauthenticated = $response->status() === 401 && $response->json('error_code') === 'UNAUTHENTICATED';

                if ($public === $unauthenticated) {
                    $failures[] = "{$method} {$route['uri']}: respondió {$response->status()}" . ($public ? ' (es pública)' : '');
                }
            }
        }

        $this->assertSame([], $failures);
        $this->assertGreaterThan(count(self::ROUTES_WITHOUT_PERMISSION), $checked);
    }

    /**
     * Las rutas sin permiso que piden token (logout y me) las usa cualquier usuario
     * autenticado, también sin rol.
     */
    public function test_routes_without_permission_are_open_to_any_authenticated_user(): void
    {
        $routes = $this->apiRoutes();
        $private = array_diff(array_keys(self::ROUTES_WITHOUT_PERMISSION), self::PUBLIC_ROUTES);
        $this->assertNotEmpty($private);

        foreach ($private as $key) {
            foreach ($routes[$key]['methods'] as $method) {
                $user = User::factory()->for($this->clinic)->create();
                $response = $this->send($user, $routes[$key], $method);

                $this->assertSame(200, $response->status(), "{$method} {$routes[$key]['uri']} respondió {$response->status()}");
            }
        }
    }

    /**
     * Pide cada método de cada ruta protegida con el usuario y compara con lo
     * esperado. Junta todas las diferencias antes de fallar, para ver la matriz
     * completa.
     *
     * @param callable(array): bool $expectedAllowed
     * @return array{0: int, 1: int} pedidos permitidos y prohibidos
     */
    private function assertAccess(User $user, callable $expectedAllowed): array
    {
        $failures = [];
        $counts = [0, 0];

        foreach ($this->apiRoutesWithPermission() as $route) {
            $this->assertNotEmpty($route['permissions'], "La ruta {$route['key']} no exige ningún permission:.");
            $allowed = $expectedAllowed($route);

            foreach ($route['methods'] as $method) {
                $counts[$allowed ? 0 : 1]++;
                $response = $this->send($user, $route, $method);

                if ($allowed && ! $this->isAllowed($response)) {
                    $failures[] = "{$method} {$route['uri']} ({$this->list($route)}): debía pasar y respondió {$response->status()}";
                }

                if (! $allowed && ! $this->isForbidden($response)) {
                    $failures[] = "{$method} {$route['uri']} ({$this->list($route)}): debía responder 403 y respondió {$response->status()}";
                }
            }
        }

        $this->assertSame([], $failures);

        return $counts;
    }

    /**
     * Usuario con un rol propio que tiene exactamente esos permisos. Se asocian por
     * id en un solo insert: givePermissionTo los busca de a uno y vacía la caché de
     * Spatie en cada llamada. Quien lo use vacía la caché una vez, al terminar.
     *
     * @param list<string> $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        static $sequence = 0;
        $sequence++;

        $ids = Permission::where('guard_name', 'api')->whereIn('name', $permissions)->pluck('id');
        $this->assertCount(count($permissions), $ids, 'Algún permiso de la ruta no existe.');

        $role = Role::create(['name' => "acceso-prueba-{$sequence}", 'guard_name' => 'api']);
        $role->permissions()->attach($ids);

        $user = User::factory()->for($this->clinic)->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    /**
     * Pide la ruta dentro de una transacción que se revierte: deshace lo que haga el
     * pedido (un DELETE, un alta). Los pedidos no tocan roles ni permisos, así que
     * la caché de Spatie sigue valiendo. El usuario autenticado se olvida antes y
     * después: si quedara el del pedido anterior, la auditoría del alta siguiente lo
     * usaría como autor.
     */
    private function send(?User $user, array $route, string $method): TestResponse
    {
        $this->app['auth']->forgetGuards();
        DB::beginTransaction();

        try {
            if ($user !== null) {
                Sanctum::actingAs($user);
            }

            return $this->json($method, $this->uri($route), []);
        } finally {
            DB::rollBack();
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
