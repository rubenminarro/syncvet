<?php

namespace Tests\Feature\Schedules;

use App\Enums\AppointmentStatus;
use App\Services\ServiceCatalogService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithSchedules;
use Tests\TestCase;

/**
 * Baja de una agenda (FASE 3, entrega 3.1; P11 y pregunta 13): física, solo sin turnos
 * (también cancelados), con sus tramos y sus bloqueos, modelo por modelo para la auditoría.
 * Al eliminarla deja de ser uso de su veterinario y de su servicio.
 */
class ScheduleDeleteTest extends TestCase
{
    use InteractsWithSchedules;

    public function test_a_schedule_without_appointments_is_deleted_with_its_hours_and_blocks(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $veterinarian = $this->veterinarianIn($branch);
        $service = $this->serviceIn($clinic);
        $schedule = $this->scheduleIn($branch, ['veterinarian_id' => $veterinarian->id, 'service_id' => $service->id]);
        $this->hoursFor($schedule, [[1, '08:00', '12:00'], [2, '08:00', '12:00']]);
        $block = $this->blockFor($schedule);
        $this->actAsScheduleManager($clinic);

        $this->deleteJson("/api/schedules/{$schedule->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Agenda eliminada correctamente.')
            ->assertJsonPath('data', null);

        $this->assertNull($this->scheduleRow($schedule->id));
        $this->assertSame(0, DB::table('schedule_hours')->count());
        $this->assertSame(0, DB::table('schedule_blocks')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('auditable_type', 'schedule')->where('event', 'deleted')->count());
        $this->assertSame(2, DB::table('audit_logs')->where('auditable_type', 'schedule_hour')->where('event', 'deleted')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('auditable_type', 'schedule_block')->where('auditable_id', $block->id)->where('event', 'deleted')->count());
        $this->getJson("/api/schedules/{$schedule->id}")->assertNotFound();

        // Sin la agenda, el veterinario y el servicio quedan sin uso.
        $this->assertFalse(app(ServiceCatalogService::class)->isInUse($service));
        $this->actingIn($clinic, ['veterinarians.destroy', 'services.destroy']);
        $this->deleteJson("/api/veterinarians/{$veterinarian->id}")->assertOk();
        $this->deleteJson("/api/services/{$service->id}")->assertOk();
    }

    public static function statuses(): array
    {
        return array_combine(
            array_map(fn (AppointmentStatus $status) => $status->value, AppointmentStatus::cases()),
            array_map(fn (AppointmentStatus $status) => [$status], AppointmentStatus::cases()),
        );
    }

    #[DataProvider('statuses')]
    public function test_a_schedule_with_an_appointment_in_any_status_is_not_deleted(AppointmentStatus $status): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $schedule = $this->scheduleIn($branch, ['service_id' => $this->serviceIn($clinic)->id]);
        $this->hoursFor($schedule, [[1, '08:00', '12:00']]);
        $this->appointmentIn($schedule, status: $status);
        $this->actAsScheduleManager($clinic);

        $response = $this->deleteJson("/api/schedules/{$schedule->id}");

        $this->assertErrorCode($response, 409, 'SCHEDULE_IN_USE');
        $response->assertJsonPath('message', 'La agenda tiene turnos y no se puede eliminar: inactivala.');
        $this->assertNotNull($this->scheduleRow($schedule->id));
        $this->assertSame(1, DB::table('schedule_hours')->count());
    }
}
