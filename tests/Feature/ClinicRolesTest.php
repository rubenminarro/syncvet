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
     * Permisos de cada rol de la clínica, ordenados: se actualiza a mano y a propósito.
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
     * Foto de los roles de la clínica (tests/Fixtures/clinic-roles.json). La matriz de
     * tests/Feature/Access sale de clinicRoles(): sin esta foto, un permiso agregado o
     * quitado por error en un rol cambiaría lo esperado y ningún test fallaría. Un
     * cambio a propósito se hace en los dos lugares.
     */
    public function test_clinic_roles_match_the_snapshot(): void
    {
        $snapshot = json_decode(file_get_contents(self::ROLES_SNAPSHOT), true, flags: JSON_THROW_ON_ERROR);
        $current = array_map(
            fn (array $role) => array_values(array_unique($role['permissions'])),
            RolesAndPermissionsSeeder::clinicRoles(),
        );

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
            'clinicRoles() no coincide con tests/Fixtures/clinic-roles.json. Si el cambio es a propósito, actualizar la foto.',
        );
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

        foreach ($routes as $route) {
            $this->assertSame(
                [$this->expectedPermission($route['key'])],
                $route['permissions'],
                "La ruta {$route['key']} ({$route['action']}) debe exigir {$this->expectedPermission($route['key'])}; si es a propósito, agregarla con su motivo en ROUTES_WITH_ANOTHER_PERMISSION.",
            );
        }

        foreach (self::ROUTES_WITH_ANOTHER_PERMISSION as $key => $exception) {
            $this->assertArrayHasKey($key, $routes, "{$key} figura en ROUTES_WITH_ANOTHER_PERMISSION pero no es una ruta con permiso.");
            $this->assertNotSame('', trim($exception['reason']), "{$key} no tiene motivo.");
        }

        $this->assertNotEmpty($routes);
    }

    /**
     * Cada excepción es una ruta que existe, tiene motivo y de verdad no pide permiso.
     */
    public function test_routes_without_permission_exist_and_have_a_reason(): void
    {
        $routes = $this->apiRoutes();

        foreach (self::ROUTES_WITHOUT_PERMISSION as $key => $reason) {
            $this->assertArrayHasKey($key, $routes, "{$key} figura sin permiso pero no es una ruta de la API.");
            $this->assertNotSame('', trim($reason), "{$key} no tiene motivo.");
            $this->assertSame([], $routes[$key]['permissions'], "{$key} figura sin permiso pero exige uno.");
        }
    }
}
