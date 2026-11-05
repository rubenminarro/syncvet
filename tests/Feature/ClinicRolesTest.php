<?php

namespace Tests\Feature;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Los roles y permisos sembrados son coherentes con el seeder y con los
 * Controllers: un permiso que no existe en PERMISSIONS deja la ruta inaccesible
 * para todos menos el administrador (Gate::before), y ese error no se ve
 * probando con el administrador.
 */
class ClinicRolesTest extends TestCase
{
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
     * Rutas de la API que no exigen un permiso de Spatie, con su motivo. Toda ruta
     * nueva sin `permission:` hace fallar el test hasta que se agregue aquí.
     *
     * @var array<string, string> nombre de la ruta (o "MÉTODO uri" si no tiene) => motivo
     */
    private const ROUTES_WITHOUT_PERMISSION = [
        'auth.login' => 'Público: es la forma de obtener un token',
        'auth.logout' => 'Cualquier usuario autenticado cierra su propia sesión',
        'auth.me' => 'Cualquier usuario autenticado ve sus propios datos',
    ];

    public function test_every_api_route_requires_a_permission(): void
    {
        $checked = 0;

        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/') && $route->uri() !== 'api') {
                continue;
            }

            $key = $route->getName() ?? implode('|', $route->methods()).' '.$route->uri();

            if (array_key_exists($key, self::ROUTES_WITHOUT_PERMISSION)) {
                continue;
            }

            $permissions = array_filter(
                $route->gatherMiddleware(),
                fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'permission:'),
            );

            $this->assertNotEmpty($permissions, "La ruta {$key} ({$route->getActionName()}) no exige ningún permission:.");
            $checked++;
        }

        $this->assertGreaterThan(0, $checked, 'No se encontraron rutas de la API.');
    }
}
