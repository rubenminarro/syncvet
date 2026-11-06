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
    ];

    /**
     * Las rutas de ROUTES_WITHOUT_PERMISSION que se usan sin token.
     *
     * @var list<string> "MÉTODOS uri"
     */
    protected const PUBLIC_ROUTES = ['POST api/login'];

    /**
     * Rutas cuyo permiso no sigue la regla del nombre (expectedPermission), con el
     * permiso que exigen y su motivo.
     *
     * @var array<string, array{permission: string, reason: string}> nombre de la ruta => excepción
     */
    protected const ROUTES_WITH_ANOTHER_PERMISSION = [
        'species.breeds.index' => ['permission' => 'species.index', 'reason' => 'R-015: las razas usan los permisos de especie'],
        'species.breeds.store' => ['permission' => 'species.store', 'reason' => 'R-015: las razas usan los permisos de especie'],
        'species.breeds.update' => ['permission' => 'species.update', 'reason' => 'R-015: las razas usan los permisos de especie'],
        'species.breeds.destroy' => ['permission' => 'species.destroy', 'reason' => 'R-015: las razas usan los permisos de especie'],
    ];

    /**
     * Regla del nombre: el permiso de una ruta es `recurso.acción`, los dos últimos
     * tramos de su nombre (`clients.index` → `clients.index`; la anidada
     * `clients.patients.store` → `patients.store`).
     */
    protected function expectedPermission(string $routeName): string
    {
        if (isset(self::ROUTES_WITH_ANOTHER_PERMISSION[$routeName])) {
            return self::ROUTES_WITH_ANOTHER_PERMISSION[$routeName]['permission'];
        }

        return implode('.', array_slice(explode('.', $routeName), -2));
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
                'permissions' => $this->routePermissions($route->gatherMiddleware()),
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
     * Los permisos de `permission:a|b` o `permission:a|b,guard`.
     *
     * @param array<int, mixed> $middleware
     * @return list<string>
     */
    private function routePermissions(array $middleware): array
    {
        $permissions = [];

        foreach ($middleware as $name) {
            if (is_string($name) && str_starts_with($name, 'permission:')) {
                $list = explode(',', substr($name, strlen('permission:')))[0];
                array_push($permissions, ...explode('|', $list));
            }
        }

        return $permissions;
    }
}
