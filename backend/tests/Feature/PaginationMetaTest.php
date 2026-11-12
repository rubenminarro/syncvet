<?php

namespace Tests\Feature;

use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAuth;
use Tests\Concerns\InteractsWithSchedules;
use Tests\TestCase;

/**
 * meta.pagination en snake_case en todos los listados paginados (entrega front-00, B3):
 * las cuatro claves de ApiResponse::paginationMeta y ninguna de las viejas en camelCase.
 */
class PaginationMetaTest extends TestCase
{
    use InteractsWithAuth;
    use InteractsWithSchedules;

    /**
     * Un caso por controller con listado paginado; {branch} y {schedule} se completan en el test.
     *
     * @return array<string, array{0: string}>
     */
    public static function paginatedLists(): array
    {
        return [
            'AppointmentController' => ['/api/appointments'],
            'AuditLogController' => ['/api/audit-logs'],
            'BranchController' => ['/api/branches'],
            'BranchWarehouseController' => ['/api/branches/{branch}/warehouses'],
            'ClientController' => ['/api/clients'],
            'PatientController' => ['/api/patients'],
            'ScheduleBlockController' => ['/api/schedules/{schedule}/blocks'],
            'ScheduleController' => ['/api/schedules'],
            'ServiceController' => ['/api/services'],
            'UserController' => ['/api/users'],
            'VeterinarianController' => ['/api/veterinarians'],
        ];
    }

    #[DataProvider('paginatedLists')]
    public function test_the_list_answers_the_pagination_in_snake_case(string $url): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $schedule = $this->scheduleIn($branch);
        Sanctum::actingAs($this->userWithRole($clinic, 'administrador'));

        $url = str_replace(['{branch}', '{schedule}'], [$branch->id, $schedule->id], $url);

        $pagination = $this->getJson("{$url}?per_page=1")
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->json('meta.pagination');

        $this->assertSame(['total', 'per_page', 'current_page', 'last_page'], array_keys($pagination));
        $this->assertSame(max($pagination['total'], 1), $pagination['last_page']);
    }
}
