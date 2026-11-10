<?php

namespace Tests\Feature\Schedules;

use App\Enums\AppointmentStatus;
use App\Enums\ServiceStatus;
use App\Enums\VeterinarianStatus;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithSchedules;
use Tests\TestCase;

/**
 * Edición de una agenda (FASE 3, entrega 3.1; preguntas 14 y 24): con turnos no cambia de
 * sucursal ni de veterinario (422 SCHEDULE_NOT_EDITABLE), el servicio sí; el veterinario o
 * el servicio que ya tiene se aceptan aunque estén inactivos; se inactiva con turnos
 * futuros, que siguen.
 */
class ScheduleUpdateTest extends TestCase
{
    use InteractsWithSchedules;

    public function test_changing_the_branch_or_the_veterinarian_with_appointments_is_rejected(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $other = $this->branchIn($clinic);
        $schedule = $this->scheduleIn($branch, ['veterinarian_id' => $this->veterinarianIn($branch, $other)->id]);
        $newVeterinarian = $this->veterinarianIn($branch, $other);
        $this->appointmentIn($schedule, status: AppointmentStatus::CANCELLED);
        $this->actAsScheduleManager($clinic);

        foreach ([['branch_id' => $other->id], ['veterinarian_id' => $newVeterinarian->id]] as $change) {
            $response = $this->patchJson("/api/schedules/{$schedule->id}", $change);

            $this->assertErrorCode($response, 422, 'SCHEDULE_NOT_EDITABLE');
            $response->assertJsonPath('message', 'La agenda ya tiene turnos y no puede cambiar de sucursal ni de veterinario: creá otra agenda e inactivá esta.');
        }

        $row = $this->scheduleRow($schedule->id);
        $this->assertSame($branch->id, (int) $row->branch_id);
        $this->assertSame($schedule->veterinarian_id, (int) $row->veterinarian_id);
    }

    public function test_resending_the_same_branch_and_veterinarian_with_appointments_passes(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $schedule = $this->scheduleIn($branch, ['veterinarian_id' => $this->veterinarianIn($branch)->id]);
        $this->appointmentIn($schedule);
        $this->actAsScheduleManager($clinic);

        $this->patchJson("/api/schedules/{$schedule->id}", [
            'branch_id' => $branch->id,
            'veterinarian_id' => $schedule->veterinarian_id,
            'name' => 'Consultorio renovado',
        ])->assertOk()->assertJsonPath('data.name', 'Consultorio renovado');
    }

    public function test_without_appointments_the_branch_and_the_veterinarian_change(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $other = $this->branchIn($clinic);
        $schedule = $this->scheduleIn($branch, ['veterinarian_id' => $this->veterinarianIn($branch)->id]);
        $newVeterinarian = $this->veterinarianIn($other);
        $this->actAsScheduleManager($clinic);

        $this->patchJson("/api/schedules/{$schedule->id}", ['branch_id' => $other->id, 'veterinarian_id' => $newVeterinarian->id])
            ->assertOk()
            ->assertJsonPath('data.branch.id', $other->id)
            ->assertJsonPath('data.veterinarian.id', $newVeterinarian->id);
    }

    public function test_moving_to_a_branch_where_the_veterinarian_does_not_work_is_rejected(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $other = $this->branchIn($clinic);
        $schedule = $this->scheduleIn($branch, ['veterinarian_id' => $this->veterinarianIn($branch)->id]);
        $this->actAsScheduleManager($clinic);

        $this->patchJson("/api/schedules/{$schedule->id}", ['branch_id' => $other->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.veterinarian_id.0', 'El veterinario no trabaja en esa sucursal.');
    }

    public function test_the_service_changes_with_appointments(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $schedule = $this->scheduleIn($branch, ['service_id' => $this->serviceIn($clinic)->id]);
        $newService = $this->serviceIn($clinic, ['name' => 'Vacunación']);
        $this->appointmentIn($schedule);
        $this->actAsScheduleManager($clinic);

        $this->patchJson("/api/schedules/{$schedule->id}", ['service_id' => $newService->id])
            ->assertOk()
            ->assertJsonPath('data.service.id', $newService->id);
    }

    public function test_the_current_veterinarian_and_service_are_accepted_although_inactive(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $veterinarian = $this->veterinarianIn($branch);
        $service = $this->serviceIn($clinic);
        $schedule = $this->scheduleIn($branch, ['veterinarian_id' => $veterinarian->id, 'service_id' => $service->id]);
        DB::table('veterinarians')->where('id', $veterinarian->id)->update(['status' => VeterinarianStatus::INACTIVE->value]);
        DB::table('services')->where('id', $service->id)->update(['status' => ServiceStatus::INACTIVE->value]);
        $this->actAsScheduleManager($clinic);

        $this->patchJson("/api/schedules/{$schedule->id}", [
            'branch_id' => $branch->id,
            'name' => $schedule->name,
            'veterinarian_id' => $veterinarian->id,
            'service_id' => $service->id,
            'slot_minutes' => 45,
        ])->assertOk()->assertJsonPath('data.slot_minutes', 45);

        // Otro veterinario o servicio inactivo, no.
        $inactiveService = $this->serviceIn($clinic, ['name' => 'Baño', 'status' => ServiceStatus::INACTIVE]);
        $this->patchJson("/api/schedules/{$schedule->id}", ['service_id' => $inactiveService->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.service_id.0', 'El servicio no existe o está inactivo.');
    }

    public function test_removing_both_the_veterinarian_and_the_service_is_rejected(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $schedule = $this->scheduleIn($branch, ['service_id' => $this->serviceIn($clinic)->id]);
        $this->actAsScheduleManager($clinic);

        $this->patchJson("/api/schedules/{$schedule->id}", ['service_id' => null])
            ->assertUnprocessable()
            ->assertJsonPath('errors.veterinarian_id.0', 'Elegí un veterinario, un servicio o los dos.');
    }

    public function test_a_schedule_with_future_appointments_is_deactivated_and_they_remain(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $schedule = $this->scheduleIn($branch, ['service_id' => $this->serviceIn($clinic)->id]);
        $appointment = $this->appointmentIn($schedule, now()->addWeek()->setTime(9, 0));
        $this->actAsScheduleManager($clinic);

        $this->patchJson("/api/schedules/{$schedule->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertSame(AppointmentStatus::CONFIRMED->value, DB::table('appointments')->where('id', $appointment)->value('status'));
        $this->assertSame(1, DB::table('audit_logs')->where('auditable_type', 'schedule')->where('auditable_id', $schedule->id)->where('event', 'updated')->count());
    }

    public function test_renaming_keeps_the_name_unique_within_the_branch(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $service = $this->serviceIn($clinic);
        $this->scheduleIn($branch, ['name' => 'Peluquería', 'service_id' => $service->id]);
        $schedule = $this->scheduleIn($branch, ['name' => 'Consultorio', 'service_id' => $service->id]);
        $this->actAsScheduleManager($clinic);

        $this->patchJson("/api/schedules/{$schedule->id}", ['name' => 'PELUQUERIA'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Ya hay una agenda con ese nombre en la sucursal.');

        $this->patchJson("/api/schedules/{$schedule->id}", ['name' => ' consultorio '])->assertOk()->assertJsonPath('data.name', 'consultorio');
    }

    public function test_the_reception_and_the_veterinarian_cannot_edit(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $schedule = $this->scheduleIn($branch, ['service_id' => $this->serviceIn($clinic)->id]);

        foreach (['recepcionista', 'veterinario'] as $role) {
            Sanctum::actingAs($this->userWithRoles($clinic, [$role]));

            $this->patchJson("/api/schedules/{$schedule->id}", ['is_active' => false])->assertForbidden();
            $this->getJson("/api/schedules/{$schedule->id}")->assertOk();
        }
    }
}
