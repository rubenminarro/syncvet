<?php

namespace Tests\Feature;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\Finder\Finder;
use Tests\Concerns\InteractsWithApiRoutes;
use Tests\TestCase;

/**
 * Los roles y permisos sembrados son coherentes con el seeder y con los
 * Controllers: un permiso que no existe en PERMISSIONS deja la ruta inaccesible
 * para todos menos el administrador (Gate::before), y ese error no se ve
 * probando con el administrador.
 */
class ClinicRolesTest extends TestCase
{
    use InteractsWithApiRoutes;

    /**
     * Permisos de cada rol (el administrador y los de la clínica), en orden alfabético:
     * se actualiza a mano y a propósito.
     */
    private const ROLES_SNAPSHOT = __DIR__ . '/../Fixtures/clinic-roles.json';

    public function test_permissions_are_not_repeated(): void
    {
        $permissions = RolesAndPermissionsSeeder::PERMISSIONS;

        $this->assertSame([], array_values(array_diff_assoc($permissions, array_unique($permissions))));
    }

    public function test_every_permission_is_seeded_with_guard_api(): void
    {
        $seeded = Permission::where('guard_name', 'api')->pluck('name')->sort()->values()->all();
        $expected = collect(RolesAndPermissionsSeeder::PERMISSIONS)->sort()->values()->all();

        $this->assertSame($expected, $seeded);
    }

    public function test_the_three_roles_exist_with_guard_api(): void
    {
        $roles = Role::where('guard_name', 'api')->pluck('name')->sort()->values()->all();

        $this->assertSame(['administrador', 'recepcionista', 'veterinario'], $roles);
    }

    public function test_administrator_has_every_permission(): void
    {
        $admin = Role::findByName('administrador', 'api');

        $this->assertCount(count(RolesAndPermissionsSeeder::PERMISSIONS), $admin->permissions);
    }

    #[DataProvider('clinicRoles')]
    public function test_clinic_role_permissions_exist(string $role, array $permissions): void
    {
        $missing = array_values(array_diff($permissions, RolesAndPermissionsSeeder::PERMISSIONS));

        $this->assertSame([], $missing, "El rol {$role} usa permisos que no están en PERMISSIONS.");
        $this->assertSame(
            collect($permissions)->unique()->sort()->values()->all(),
            Role::findByName($role, 'api')->permissions->pluck('name')->sort()->values()->all(),
        );
    }

    /**
     * Foto de los roles sembrados (tests/Fixtures/clinic-roles.json): el administrador
     * con todos los permisos y los roles de la clínica. La matriz de
     * tests/Feature/Access sale de clinicRoles(): sin esta foto, un permiso agregado o
     * quitado por error en un rol cambiaría lo esperado y ningún test fallaría. Un
     * cambio a propósito se hace en los dos lugares. Se compara con los roles de la
     * base, que es lo que usa la API.
     */
    public function test_roles_match_the_snapshot(): void
    {
        $snapshot = json_decode(file_get_contents(self::ROLES_SNAPSHOT), true, flags: JSON_THROW_ON_ERROR);
        $current = Role::where('guard_name', 'api')->with('permissions')->get()
            ->mapWithKeys(fn (Role $role) => [$role->name => $role->permissions->pluck('name')->sort()->values()->all()])
            ->all();

        $differences = [];

        foreach (array_unique([...array_keys($current), ...array_keys($snapshot)]) as $role) {
            if (! array_key_exists($role, $snapshot)) {
                $differences[] = "el rol {$role} no está en la foto";
                continue;
            }

            if (! array_key_exists($role, $current)) {
                $differences[] = "el rol {$role} está en la foto pero ya no existe";
                continue;
            }

            foreach (array_diff($current[$role], $snapshot[$role]) as $permission) {
                $differences[] = "{$role}: sobra {$permission}";
            }

            foreach (array_diff($snapshot[$role], $current[$role]) as $permission) {
                $differences[] = "{$role}: falta {$permission}";
            }
        }

        sort($differences);

        $this->assertSame(
            [],
            $differences,
            'Los roles no coinciden con tests/Fixtures/clinic-roles.json. Si el cambio es a propósito, actualizar la foto.',
        );
    }

    /**
     * La foto está ordenada (roles y permisos, alfabéticamente) y sin repetidos: un
     * cambio se ve como una línea agregada o quitada en su lugar, no como un bloque
     * reescrito, y no se puede tapar uno con otro repetido.
     */
    public function test_the_roles_snapshot_is_sorted_and_without_duplicates(): void
    {
        $snapshot = json_decode(file_get_contents(self::ROLES_SNAPSHOT), true, flags: JSON_THROW_ON_ERROR);

        $roles = array_keys($snapshot);
        $sortedRoles = $roles;
        sort($sortedRoles, SORT_STRING);
        $this->assertSame($sortedRoles, $roles, 'Los roles de la foto deben estar en orden alfabético.');

        foreach ($snapshot as $role => $permissions) {
            $sorted = array_values(array_unique($permissions));
            sort($sorted, SORT_STRING);

            $this->assertSame($sorted, $permissions, "Los permisos de {$role} en la foto deben estar en orden alfabético y sin repetidos.");
        }

        $this->assertSame(['administrador', 'recepcionista', 'veterinario'], $roles);
    }

    public static function clinicRoles(): array
    {
        return collect(RolesAndPermissionsSeeder::clinicRoles())
            ->map(fn (array $role, string $name) => [$name, $role['permissions']])
            ->all();
    }

    public function test_every_controller_permission_exists(): void
    {
        $used = [];

        foreach (Finder::create()->files()->in(app_path('Http/Controllers'))->name('*.php') as $file) {
            $relative = str_replace(['/', '\\', '.php'], [' ', ' ', ''], $file->getRelativePathname());
            $class = 'App\\Http\\Controllers\\'.str_replace(' ', '\\', $relative);

            if (! is_subclass_of($class, HasMiddleware::class)) {
                continue;
            }

            foreach ($class::middleware() as $middleware) {
                $name = $middleware instanceof Middleware ? $middleware->middleware : $middleware;

                if (is_string($name) && str_starts_with($name, 'permission:')) {
                    foreach (explode('|', substr($name, strlen('permission:'))) as $permission) {
                        $used[] = "{$class}: {$permission}";
                        $this->assertContains($permission, RolesAndPermissionsSeeder::PERMISSIONS, "{$class} usa {$permission}, que no está en PERMISSIONS.");
                    }
                }
            }
        }

        $this->assertNotEmpty($used, 'No se encontraron permisos en los Controllers.');
    }

    /**
     * Toda ruta de la API exige un `permission:`, salvo las de
     * ROUTES_WITHOUT_PERMISSION (InteractsWithApiRoutes), cada una con su motivo.
     */
    public function test_every_api_route_requires_a_permission(): void
    {
        $routes = $this->apiRoutesWithPermission();

        foreach ($routes as $route) {
            $this->assertNotEmpty($route['permissions'], "La ruta {$route['key']} ({$route['action']}) no exige ningún permission:.");
        }

        $this->assertNotEmpty($routes, 'No se encontraron rutas de la API.');
    }

    /**
     * El permiso de cada ruta es el de su nombre (`recurso.acción`): un Controller que
     * pide otro permiso que existe (`clients.update` en `destroy`) no lo detecta la
     * matriz de tests/Feature/Access, que lee el permiso del propio Controller.
     */
    public function test_each_route_requires_the_permission_of_its_name(): void
    {
        $routes = $this->apiRoutesWithPermission();
        $names = [];

        foreach ($routes as $route) {
            $this->assertNotNull($route['name'], "La ruta {$route['key']} ({$route['action']}) no tiene nombre.");
            $names[] = $route['name'];

            $this->assertSame(
                [$this->expectedPermission($route['name'])],
                $route['permissions'],
                "La ruta {$route['key']} ({$route['action']}) debe exigir {$this->expectedPermission($route['name'])}; si es a propósito, agregarla con su motivo en ROUTES_WITH_ANOTHER_PERMISSION.",
            );
        }

        foreach (self::ROUTES_WITH_ANOTHER_PERMISSION as $name => $exception) {
            $this->assertContains($name, $names, "{$name} figura en ROUTES_WITH_ANOTHER_PERMISSION pero no es una ruta con permiso.");
            $this->assertNotSame('', trim($exception['reason']), "{$name} no tiene motivo.");
        }

        $this->assertNotEmpty($routes);
    }

    /**
     * Cada ruta de la API tiene un nombre propio. Laravel acepta dos rutas con el
     * mismo nombre (route() lleva a la última), y la regla del nombre les pediría a
     * las dos el mismo permiso: una ruta copiada sin cambiarle el nombre quedaría
     * con el permiso de otra acción.
     */
    public function test_every_api_route_has_its_own_name(): void
    {
        $byName = [];

        foreach ($this->apiRoutes() as $route) {
            $this->assertNotNull($route['name'], "La ruta {$route['key']} ({$route['action']}) no tiene nombre.");
            $byName[$route['name']][] = $route['key'];
        }

        $repeated = array_filter($byName, fn (array $keys) => count($keys) > 1);

        $this->assertSame([], $repeated, 'Rutas de la API con el mismo nombre: darle a cada una el suyo.');
    }

    /**
     * Cada excepción es una ruta que existe, tiene motivo y de verdad no pide permiso.
     * Las públicas son excepciones.
     */
    public function test_routes_without_permission_exist_and_have_a_reason(): void
    {
        $routes = $this->apiRoutes();

        foreach (self::ROUTES_WITHOUT_PERMISSION as $key => $reason) {
            $this->assertArrayHasKey($key, $routes, "{$key} figura sin permiso pero no es una ruta de la API.");
            $this->assertNotSame('', trim($reason), "{$key} no tiene motivo.");
            $this->assertSame([], $routes[$key]['permissions'], "{$key} figura sin permiso pero exige uno.");
        }

        foreach (self::PUBLIC_ROUTES as $key) {
            $this->assertArrayHasKey($key, self::ROUTES_WITHOUT_PERMISSION, "{$key} figura como pública pero no en ROUTES_WITHOUT_PERMISSION.");
        }
    }
}
