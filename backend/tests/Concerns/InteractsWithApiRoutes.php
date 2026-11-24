<?php

namespace Tests\Concerns;

/**
 * Las rutas reales de la API y el permiso que exige cada una (el `permission:` de
 * su Controller), para los tests de permisos (ClinicRolesTest) y de acceso por rol
 * (tests/Feature/Access). Una ruta nueva queda cubierta sin tocar los tests.
 *
 * Cada ruta se identifica por sus métodos y su URI (`GET|HEAD api/clients`), no por
 * su nombre: dos rutas con el mismo nombre se pisarían y una quedaría sin revisar.
 * ClinicRolesTest exige además que cada ruta tenga un nombre propio.
 */
trait InteractsWithApiRoutes
{
    /**
     * Rutas de la API que no exigen un permiso de Spatie, con su motivo. Toda ruta
     * nueva sin `permission:` hace fallar ClinicRolesTest hasta que se agregue aquí.
     * Salvo las de PUBLIC_ROUTES, todas exigen token (RoleAccessTest).
     *
     * @var array<string, string> "MÉTODOS uri" => motivo
     */
    protected const ROUTES_WITHOUT_PERMISSION = [
        'POST api/login' => 'Público: es la forma de obtener un token',
        'POST api/logout' => 'Cualquier usuario autenticado cierra su propia sesión',
        'GET|HEAD api/me' => 'Cualquier usuario autenticado ve sus propios datos',
        'PATCH api/me/current-branch' => 'Cualquier usuario autenticado elige su sucursal actual (FASE 2, entrega 2.2b)',
        'PATCH api/me/password' => 'Cualquier usuario autenticado cambia su propia contraseña (FASE 2, entrega 2.2b)',
    ];

    /**
     * Las rutas de ROUTES_WITHOUT_PERMISSION que se usan sin token.
     *
     * @var list<string> "MÉTODOS uri"
     */
    protected const PUBLIC_ROUTES = ['POST api/login'];

    /**
     * Rutas cuyo permiso no sigue la regla del nombre (expectedPermissions), con el
     * permiso que exigen y su motivo. Una lista en `permission` son varios permisos
     * a la vez (un `permission:` por cada uno: hacen falta todos).
     *
     * @var array<string, array{permission: string|list<string>, reason: string}> nombre de la ruta => excepción
     */
    protected const ROUTES_WITH_ANOTHER_PERMISSION = [
        'patients.history' => ['permission' => ['consultations.index', 'patient-applications.index'], 'reason' => 'FASE 4, entrega 4.6 (R-099): el historial reúne consultas y aplicaciones, sin permiso propio: exige los dos'],
        'species.breeds.index' => ['permission' => 'species.index', 'reason' => 'R-015: las razas usan los permisos de especie'],
        'species.breeds.store' => ['permission' => 'species.store', 'reason' => 'R-015: las razas usan los permisos de especie'],
        'species.breeds.update' => ['permission' => 'species.update', 'reason' => 'R-015: las razas usan los permisos de especie'],
        'species.breeds.destroy' => ['permission' => 'species.destroy', 'reason' => 'R-015: las razas usan los permisos de especie'],
        'appointments.undo-no-show' => ['permission' => 'appointments.no-show', 'reason' => 'Pregunta 19 de la FASE 3 (R-048): el ausente lo deshacen quienes lo marcan, el administrador y la recepción'],
    ];

    /**
     * Regla del nombre: el permiso de una ruta es `recurso.acción`, los dos últimos
     * tramos de su nombre (`clients.index` → `clients.index`; la anidada
     * `clients.patients.store` → `patients.store`).
     *
     * @return list<string> los permisos que exige, todos a la vez (casi siempre uno)
     */
    protected function expectedPermissions(string $routeName): array
    {
        if (isset(self::ROUTES_WITH_ANOTHER_PERMISSION[$routeName])) {
            return (array) self::ROUTES_WITH_ANOTHER_PERMISSION[$routeName]['permission'];
        }

        return [implode('.', array_slice(explode('.', $routeName), -2))];
    }

    /**
     * @return array<string, array{key: string, name: ?string, methods: list<string>, uri: string, action: string, permissions: list<string>}>
     */
    protected function apiRoutes(): array
    {
        $routes = [];

        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/') && $route->uri() !== 'api') {
                continue;
            }

            $key = implode('|', $route->methods()) . ' ' . $route->uri();

            $routes[$key] = [
                'key' => $key,
                'name' => $route->getName(),
                // HEAD es el mismo GET (Laravel lo agrega solo): se prueban los demás, todos.
                'methods' => array_values(array_diff($route->methods(), ['HEAD'])),
                'uri' => $route->uri(),
                'action' => $route->getActionName(),
                'permissions' => array_merge([], ...$this->permissionGroups($route->gatherMiddleware())),
                // Un grupo por `permission:`: hace falta uno de cada grupo (los de un grupo, `a|b`, son uno u otro).
                'permission_groups' => $this->permissionGroups($route->gatherMiddleware()),
            ];
        }

        return $routes;
    }

    /**
     * Las rutas que exigen permiso: todas menos ROUTES_WITHOUT_PERMISSION.
     *
     * @return array<string, array{key: string, name: ?string, methods: list<string>, uri: string, action: string, permissions: list<string>}>
     */
    protected function apiRoutesWithPermission(): array
    {
        return array_diff_key($this->apiRoutes(), self::ROUTES_WITHOUT_PERMISSION);
    }

    /**
     * Los permisos de cada `permission:a|b` o `permission:a|b,guard`, un grupo por middleware.
     *
     * @param array<int, mixed> $middleware
     * @return list<list<string>>
     */
    private function permissionGroups(array $middleware): array
    {
        $groups = [];

        foreach ($middleware as $name) {
            if (is_string($name) && str_starts_with($name, 'permission:')) {
                $groups[] = explode('|', explode(',', substr($name, strlen('permission:')))[0]);
            }
        }

        return $groups;
    }

    /**
     * true si los permisos alcanzan para la ruta: uno de cada grupo.
     *
     * @param array{permission_groups: list<list<string>>} $route
     * @param list<string> $permissions
     */
    protected function permissionsReach(array $route, array $permissions): bool
    {
        foreach ($route['permission_groups'] as $group) {
            if (array_intersect($group, $permissions) === []) {
                return false;
            }
        }

        return $route['permission_groups'] !== [];
    }
}
