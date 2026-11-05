<?php

namespace Tests\Concerns;

/**
 * Las rutas reales de la API y el permiso que exige cada una (el `permission:` de
 * su Controller), para los tests de permisos (ClinicRolesTest) y de acceso por rol
 * (tests/Feature/Access). Una ruta nueva queda cubierta sin tocar los tests.
 */
trait InteractsWithApiRoutes
{
    /**
     * Rutas de la API que no exigen un permiso de Spatie, con su motivo. Toda ruta
     * nueva sin `permission:` hace fallar ClinicRolesTest hasta que se agregue aquí.
     *
     * @var array<string, string> nombre de la ruta (o "MÉTODO uri" si no tiene) => motivo
     */
    protected const ROUTES_WITHOUT_PERMISSION = [
        'auth.login' => 'Público: es la forma de obtener un token',
        'auth.logout' => 'Cualquier usuario autenticado cierra su propia sesión',
        'auth.me' => 'Cualquier usuario autenticado ve sus propios datos',
    ];

    /**
     * Rutas cuyo permiso no sigue la regla del nombre (expectedPermission), con el
     * permiso que exigen y su motivo. Vacía: hoy todas la siguen.
     *
     * @var array<string, array{permission: string, reason: string}>
     */
    protected const ROUTES_WITH_ANOTHER_PERMISSION = [];

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
     * @return array<string, array{key: string, method: string, uri: string, action: string, permissions: list<string>}>
     */
    protected function apiRoutes(): array
    {
        $routes = [];

        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/') && $route->uri() !== 'api') {
                continue;
            }

            $key = $route->getName() ?? implode('|', $route->methods()) . ' ' . $route->uri();
            $methods = array_values(array_diff($route->methods(), ['HEAD']));

            $routes[$key] = [
                'key' => $key,
                // PUT|PATCH se prueba con PATCH: las actualizaciones son parciales (CLAUDE.md §3).
                'method' => in_array('PATCH', $methods, true) ? 'PATCH' : $methods[0],
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
     * @return array<string, array{key: string, method: string, uri: string, action: string, permissions: list<string>}>
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
