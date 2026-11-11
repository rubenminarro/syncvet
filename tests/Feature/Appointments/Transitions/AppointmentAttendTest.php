<?php

namespace Tests\Feature\Appointments\Transitions;

use App\Enums\AppointmentStatus;
use App\Models\Schedule;
use App\Models\User;
use App\Models\Veterinarian;
use App\Support\CurrentClinic;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Marcar atendido (FASE 3, entrega 3.2b; pregunta 18): un usuario con ficha de veterinario y sin
 * appointments.update marca solo los turnos de su ficha o de agendas sin veterinario (si no,
 * 403); el administrador y quien tiene appointments.update, cualquiera. La recepción no tiene
 * appointments.attend (403).
 */
class AppointmentAttendTest extends TestCase
{
    use InteractsWithAppointments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeToday('11:00:00');
    }

    public function test_the_veterinarian_attends_own_and_unassigned_appointments_only(): void
    {
        [$own, $other, $free] = $this->appointments();

        Sanctum::actingAs($this->veterinarianUserOf($own['schedule']));
        $this->transition($own['id'], 'attend')->assertOk()->assertJsonPath('data.status.value', 'attended');
        $this->transition($free['id'], 'attend')->assertOk();

        $this->assertErrorCode($this->transition($other['id'], 'attend'), 403, 'FORBIDDEN');
        $this->assertSame('checked_in', $this->appointmentRow($other['id'])->status);
    }

    public function test_reception_cannot_attend(): void
    {
        [$own] = $this->appointments();

        Sanctum::actingAs($this->userWithRoles($own['schedule']->clinic, ['recepcionista']));

        $this->assertErrorCode($this->transition($own['id'], 'attend'), 403, 'FORBIDDEN');
        $this->assertSame('checked_in', $this->appointmentRow($own['id'])->status);
    }

    /**
     * El administrador (Gate::before) y quien tiene appointments.update, aunque tenga ficha,
     * marcan cualquiera.
     */
    public function test_the_administrator_and_a_user_with_update_attend_any(): void
    {
        [$own, $other] = $this->appointments();
        $clinic = $own['schedule']->clinic;

        Sanctum::actingAs($this->userWithRoles($clinic, ['administrador']));
        $this->transition($other['id'], 'attend')->assertOk();

        $withUpdate = $this->veterinarianUserOf($own['schedule']);
        $withUpdate->givePermissionTo('appointments.update');
        Sanctum::actingAs($withUpdate->fresh());
        $third = $this->appointmentIn($other['schedule'], $this->today('12:00'), AppointmentStatus::CHECKED_IN);
        $this->transition($third, 'attend')->assertOk();
    }

    /**
     * El permiso de la ficha va antes que el estado: un turno ajeno sin fichar también da 403.
     */
    public function test_the_ownership_is_checked_before_the_status(): void
    {
        [$own, $other] = $this->appointments();
        $this->setStatus($other['id'], AppointmentStatus::CONFIRMED);

        Sanctum::actingAs($this->veterinarianUserOf($own['schedule']));

        $this->assertErrorCode($this->transition($other['id'], 'attend'), 403, 'FORBIDDEN');
    }

    /**
     * Tres turnos fichados de hoy: de una agenda con veterinario, de otra con otro veterinario y
     * de una agenda sin veterinario.
     *
     * @return list<array{schedule: Schedule, id: int}>
     */
    private function appointments(): array
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $result = [];

        foreach ([[], [], ['veterinarian_id' => null, 'service_id' => $this->serviceIn($clinic)->id]] as $attributes) {
            $schedule = $this->openSchedule($branch, $attributes);
            $result[] = ['schedule' => $schedule, 'id' => $this->appointmentIn($schedule, $this->today('10:00'), AppointmentStatus::CHECKED_IN)];
        }

        return $result;
    }

    private function veterinarianUserOf(Schedule $schedule): User
    {
        return CurrentClinic::run($schedule->clinic, fn () => Veterinarian::findOrFail($schedule->veterinarian_id)->user);
    }
}
