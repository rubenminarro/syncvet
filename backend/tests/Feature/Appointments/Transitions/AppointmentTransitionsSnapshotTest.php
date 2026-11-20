<?php

namespace Tests\Feature\Appointments\Transitions;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentTransition;
use App\Http\Controllers\Api\AppointmentTransitionController;
use App\Services\AppointmentService;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use ReflectionClassConstant;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * La foto de los estados y las transiciones del turno (tests/Fixtures/appointment-transitions.json; deuda 27, cierre de la F3
 * del frontend): los estados (si ocupan el horario y si se editan), y cada transición con sus orígenes, su destino, el tramo
 * de su ruta, el permiso de su middleware y los roles que lo tienen. Se arma desde el código (AppointmentTransition, las rutas,
 * AppointmentTransitionController y los roles sembrados) y se compara con el JSON, que el frontend lee tal cual
 * (frontend/src/features/appointments/transitions.test.ts): un cambio en el backend rompe este test y, una vez actualizada la
 * foto, el del frontend. Las reglas del servicio (el turno ajeno, el día, la hora del ausente, el paciente, deshacer el ausente)
 * no van en la foto: las cubren los tests de esta carpeta.
 *
 * Un cambio a propósito se vuelca con UPDATE_TRANSITIONS_SNAPSHOT=1 (escribe el JSON y el test queda incompleto) y se revisa en
 * el diff.
 */
class AppointmentTransitionsSnapshotTest extends TestCase
{
    private const SNAPSHOT = __DIR__ . '/../../../Fixtures/appointment-transitions.json';

    public function test_the_transitions_match_the_snapshot(): void
    {
        $current = $this->currentTable();

        if (getenv('UPDATE_TRANSITIONS_SNAPSHOT') === '1') {
            file_put_contents(self::SNAPSHOT, $this->encode($current));
            $this->markTestIncomplete('Foto de las transiciones actualizada: revisala en el diff.');
        }

        $this->assertFileExists(self::SNAPSHOT);
        // Se comparan los datos, no el texto: el fin de línea del archivo puede cambiar con Git en Windows.
        $this->assertSame(
            $current,
            json_decode(file_get_contents(self::SNAPSHOT), true, flags: JSON_THROW_ON_ERROR),
            'Las transiciones no coinciden con tests/Fixtures/appointment-transitions.json. Si el cambio es a propósito, '
            . 'volcala con UPDATE_TRANSITIONS_SNAPSHOT=1 y actualizá transitions.ts del frontend.',
        );
    }

    public function test_every_transition_has_its_route_and_its_permission(): void
    {
        foreach ($this->currentTable()['transitions'] as $transition) {
            $this->assertNotSame('', $transition['path'], $transition['name']);
            $this->assertNotSame('', $transition['permission'], $transition['name']);
            $this->assertContains('administrador', $transition['roles'], $transition['name']);
        }
    }

    /**
     * @return array{statuses: list<array<string, mixed>>, transitions: list<array<string, mixed>>}
     */
    private function currentTable(): array
    {
        $editable = (new ReflectionClassConstant(AppointmentService::class, 'EDITABLE'))->getValue();
        $permissions = $this->controllerPermissions();
        $routes = $this->controllerRoutes();
        $roles = Role::where('guard_name', 'api')->with('permissions')->orderBy('name')->get();

        $statuses = array_map(fn (AppointmentStatus $status) => [
            'value' => $status->value,
            'label' => $status->label(),
            'occupies_slot' => $status->occupiesSlot(),
            'editable' => in_array($status, $editable, true),
        ], AppointmentStatus::cases());

        $transitions = array_map(function (AppointmentTransition $transition) use ($permissions, $routes, $roles) {
            $method = Str::camel($transition->value);
            $permission = $permissions[$method] ?? '';

            return [
                'name' => $transition->value,
                'from' => array_map(fn (AppointmentStatus $status) => $status->value, $transition->origins()),
                'to' => $transition->to()->value,
                'path' => $routes[$method] ?? '',
                'permission' => $permission,
                'roles' => $roles
                    ->filter(fn (Role $role) => $role->permissions->contains('name', $permission))
                    ->pluck('name')->values()->all(),
            ];
        }, AppointmentTransition::cases());

        return ['statuses' => $statuses, 'transitions' => $transitions];
    }

    /**
     * El permiso de cada método del controlador (su middleware `permission:`).
     *
     * @return array<string, string>
     */
    private function controllerPermissions(): array
    {
        $permissions = [];

        foreach (AppointmentTransitionController::middleware() as $middleware) {
            $this->assertInstanceOf(Middleware::class, $middleware);

            foreach ($middleware->only ?? [] as $method) {
                $permissions[$method] = Str::after((string) $middleware->middleware, 'permission:');
            }
        }

        return $permissions;
    }

    /**
     * El último tramo de la ruta POST de cada método del controlador (appointments/{appointment}/<tramo>).
     *
     * @return array<string, string>
     */
    private function controllerRoutes(): array
    {
        $routes = [];

        /** @var RoutingRoute $route */
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $action = $route->getActionName();

            if (! str_starts_with($action, AppointmentTransitionController::class . '@')) {
                continue;
            }

            $this->assertSame(['POST'], $route->methods(), $route->uri());
            $this->assertStringStartsWith('api/appointments/{appointment}/', $route->uri());
            $routes[Str::after($action, '@')] = Str::afterLast($route->uri(), '/');
        }

        return $routes;
    }

    private function encode(array $table): string
    {
        return json_encode($table, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }
}
