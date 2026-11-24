<?php

namespace Tests\Concerns;

use App\Enums\AppointmentStatus;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Schedule;
use App\Models\User;
use App\Models\Veterinarian;
use App\Support\CurrentClinic;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/**
 * La consulta (FASE 4, entrega 4.1). Cada test arma una clínica con su sucursal por defecto y una
 * agenda con un veterinario (setUpClinic), y abre consultas por la API con el usuario de esa ficha
 * (rol veterinario), con el administrador o con la recepción. El reloj se congela el martes
 * 10/11/2026 a las 07:00 de Asunción (freezeToday, de InteractsWithAppointments).
 */
trait InteractsWithConsultations
{
    use InteractsWithAppointments;
    use InteractsWithClinicalRecords;
    use InteractsWithUsers;
    use InteractsWithVeterinarians;

    protected Clinic $clinic;

    protected Branch $branch;

    protected Schedule $schedule;

    protected Veterinarian $vet;

    protected function setUpClinic(): void
    {
        $this->freezeToday('09:00:00');
        $this->clinic = $this->createClinic();
        $this->branch = $this->defaultBranchIn($this->clinic);
        $this->schedule = $this->openSchedule($this->branch);
        $this->vet = CurrentClinic::run($this->clinic, fn () => Veterinarian::findOrFail($this->schedule->veterinarian_id));
    }

    /**
     * El usuario de la ficha (rol veterinario), con la sucursal actual.
     */
    protected function actAsVet(?Veterinarian $vet = null): User
    {
        $user = ($vet ?? $this->vet)->user;
        $user->forceFill(['current_branch_id' => $this->branch->id])->save();
        Sanctum::actingAs($user);

        return $user;
    }

    protected function actAsAdmin(bool $withBranch = true): User
    {
        $user = $this->userWithRoles($this->clinic, ['administrador'], [$this->branch]);

        if ($withBranch) {
            $user->forceFill(['current_branch_id' => $this->branch->id])->save();
        }

        Sanctum::actingAs($user);

        return $user;
    }

    protected function actAsReceptionist(): User
    {
        $user = $this->userWithRoles($this->clinic, ['recepcionista'], [$this->branch]);
        $user->forceFill(['current_branch_id' => $this->branch->id])->save();
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * Un turno de hoy a las 08:30 de la agenda (con un paciente nuevo), fichado a las 08:40 salvo
     * que $status diga otra cosa.
     */
    protected function checkedIn(?Schedule $schedule = null, AppointmentStatus $status = AppointmentStatus::CHECKED_IN, ?CarbonInterface $startsAt = null): int
    {
        $startsAt ??= $this->today('08:30');
        $id = $this->appointmentIn($schedule ?? $this->schedule, $startsAt, $status);
        $fields = ['reason' => 'Control anual'];

        if (in_array($status, [AppointmentStatus::CHECKED_IN, AppointmentStatus::ATTENDED], true)) {
            $fields['checked_in_at'] = $startsAt->copy()->addMinutes(10)->format('Y-m-d H:i:s');
        }

        if ($status === AppointmentStatus::ATTENDED) {
            $fields['attended_at'] = $startsAt->copy()->addMinutes(20)->format('Y-m-d H:i:s');
        }

        DB::table('appointments')->where('id', $id)->update($fields);

        return $id;
    }

    protected function openConsultation(array $body): TestResponse
    {
        return $this->postJson('/api/consultations', $body);
    }

    /**
     * Abre por la API y devuelve el id (201).
     */
    protected function opened(array $body): int
    {
        return $this->openConsultation($body)->assertCreated()->json('data.id');
    }

    protected function consultationRow(int $id): object
    {
        return DB::table('consultations')->where('id', $id)->first();
    }

    protected function patientIdOf(int $appointmentId): int
    {
        return (int) DB::table('appointments')->where('id', $appointmentId)->value('patient_id');
    }
}
