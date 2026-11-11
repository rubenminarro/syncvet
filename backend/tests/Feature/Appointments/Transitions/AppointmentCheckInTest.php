<?php

namespace Tests\Feature\Appointments\Transitions;

use App\Enums\AppointmentStatus;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Fichar (FASE 3, entrega 3.2b): el turno tiene paciente (P4; si no, 422
 * APPOINTMENT_PATIENT_REQUIRED), no fallecido, y es de hoy en Asunción (si no, 422
 * APPOINTMENT_CHECK_IN_NOT_TODAY). El veterinario ficha solo los turnos de su ficha o de agendas
 * sin veterinario (pregunta 18, extendida al fichado por el usuario); la recepción, cualquiera.
 */
class AppointmentCheckInTest extends TestCase
{
    use InteractsWithAppointments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeToday('09:00:00');
    }

    public function test_without_a_patient_it_is_rejected(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $id = $this->appointmentIn($schedule, $this->today('10:00'));
        DB::table('appointments')->where('id', $id)->update([
            'patient_id' => null, 'client_id' => null, 'contact_name' => 'Juan, el de la perra Luna', 'contact_phone' => '981123456',
        ]);
        $this->actingIn($clinic, self::TRANSITION_PERMISSIONS);

        $response = $this->transition($id, 'check-in');

        $this->assertErrorCode($response, 422, 'APPOINTMENT_PATIENT_REQUIRED');
        $response->assertJsonPath('message', 'Para fichar el turno, cargá el paciente.');
        $this->assertSame('confirmed', $this->appointmentRow($id)->status);

        // Con un cliente y sin paciente, tampoco.
        $client = $this->clientIn($clinic);
        DB::table('appointments')->where('id', $id)->update(['client_id' => $client->id, 'contact_name' => null, 'contact_phone' => null]);
        $this->assertErrorCode($this->transition($id, 'check-in'), 422, 'APPOINTMENT_PATIENT_REQUIRED');
    }

    public function test_a_deceased_patient_is_rejected(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $id = $this->appointmentIn($schedule, $this->today('10:00'));
        DB::table('patients')->where('id', $this->appointmentRow($id)->patient_id)->update(['deceased_at' => '2026-11-09 18:00:00']);
        $this->actingIn($clinic, self::TRANSITION_PERMISSIONS);

        $this->transition($id, 'check-in')
            ->assertUnprocessable()
            ->assertJsonPath('errors.patient_id.0', 'El paciente falleció: no se puede fichar el turno.');
        $this->assertSame('confirmed', $this->appointmentRow($id)->status);
    }

    public function test_only_on_the_day_of_the_appointment(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $tomorrow = $this->appointmentIn($schedule, $this->today('10:00')->addDay());
        $yesterday = $this->appointmentIn($schedule, $this->today('10:00')->subDay());
        $later = $this->appointmentIn($schedule, $this->today('19:30'));
        $this->actingIn($clinic, self::TRANSITION_PERMISSIONS);

        foreach ([$tomorrow, $yesterday] as $id) {
            $response = $this->transition($id, 'check-in');
            $this->assertErrorCode($response, 422, 'APPOINTMENT_CHECK_IN_NOT_TODAY');
            $response->assertJsonPath('message', 'Solo se fichan los turnos del día.');
        }

        // Más tarde hoy: se ficha antes de la hora (el cliente llegó temprano).
        $this->transition($later, 'check-in')->assertOk();
    }

    /**
     * A las 22:30 de Asunción ya es el día siguiente en UTC (01:30 del 11): hoy sigue siendo el
     * martes 10 de Asunción. Un turno de hoy a las 21:00 se ficha; uno de mañana a las 00:30
     * (03:30 UTC del 11, el mismo día UTC que "ahora"), no.
     */
    public function test_near_midnight_the_day_is_the_one_of_asuncion(): void
    {
        $this->freezeToday('22:30:00');
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $tonight = $this->appointmentIn($schedule, $this->today('21:00'));
        $afterMidnight = $this->appointmentIn($schedule, $this->today('00:30')->addDay());
        $this->actingIn($clinic, self::TRANSITION_PERMISSIONS);

        $this->transition($tonight, 'check-in')->assertOk();
        $this->assertErrorCode($this->transition($afterMidnight, 'check-in'), 422, 'APPOINTMENT_CHECK_IN_NOT_TODAY');
    }

    /**
     * Pregunta 18 (extendida al fichado): el veterinario ficha los turnos de su ficha y los de
     * agendas sin veterinario; los de otra ficha, 403 sin cambios. La recepción, cualquiera.
     */
    public function test_the_veterinarian_checks_in_only_own_appointments_and_reception_any(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $own = $this->openSchedule($branch);
        $other = $this->openSchedule($branch);
        $withoutVeterinarian = $this->openSchedule($branch, ['veterinarian_id' => null, 'service_id' => $this->serviceIn($clinic)->id]);
        $ownId = $this->appointmentIn($own, $this->today('10:00'));
        $otherId = $this->appointmentIn($other, $this->today('10:00'));
        $freeId = $this->appointmentIn($withoutVeterinarian, $this->today('11:00'));

        Sanctum::actingAs($this->veterinarianUserOf($own));
        $this->transition($ownId, 'check-in')->assertOk();
        $this->transition($freeId, 'check-in')->assertOk();
        $this->assertNotYours($this->transition($otherId, 'check-in'));
        $this->assertSame('confirmed', $this->appointmentRow($otherId)->status);

        Sanctum::actingAs($this->userWithRoles($clinic, ['recepcionista']));
        $this->transition($otherId, 'check-in')->assertOk();
    }

    /**
     * Por el rol: un veterinario sin ficha, o con la ficha eliminada, ficha solo los turnos de
     * agendas sin veterinario; los demás (también los de su ficha eliminada), 403
     * APPOINTMENT_NOT_YOURS sin cambios.
     */
    #[DataProvider('veterinariansWithoutRecord')]
    public function test_a_veterinarian_without_a_record_checks_in_only_unassigned_appointments(bool $deletedRecord): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $schedule = $this->openSchedule($branch);
        $withoutVeterinarian = $this->openSchedule($branch, ['veterinarian_id' => null, 'service_id' => $this->serviceIn($clinic)->id]);
        $assignedId = $this->appointmentIn($schedule, $this->today('10:00'));
        $freeId = $this->appointmentIn($withoutVeterinarian, $this->today('11:00'));

        if ($deletedRecord) {
            $user = $this->veterinarianUserOf($schedule);
            DB::table('veterinarians')->where('id', $schedule->veterinarian_id)->update(['deleted_at' => now()]);
        } else {
            $user = $this->userWithRoles($clinic, ['veterinario']);
        }

        Sanctum::actingAs($user);

        $this->assertNotYours($this->transition($assignedId, 'check-in'));
        $this->assertSame('confirmed', $this->appointmentRow($assignedId)->status);
        $this->transition($freeId, 'check-in')->assertOk();
    }

    public static function veterinariansWithoutRecord(): array
    {
        return ['sin ficha' => [false], 'con la ficha eliminada' => [true]];
    }

    /**
     * A las 23:50 de Asunción (02:50 UTC del 11) se ficha un turno de hoy a las 23:30.
     */
    public function test_at_23_50_a_turn_of_23_30_is_checked_in(): void
    {
        $this->freezeToday('23:50:00');
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $id = $this->appointmentIn($schedule, $this->today('23:30'));
        $this->actingIn($clinic, self::TRANSITION_PERMISSIONS);

        $this->transition($id, 'check-in')->assertOk();
    }

    /**
     * A las 00:10 de Asunción del 11 (03:10 UTC del 11), un turno de ayer a las 23:30 (02:30 UTC
     * del 11, el mismo día UTC) ya no se ficha: el día es el de Asunción.
     */
    public function test_at_00_10_a_turn_of_yesterday_23_30_is_not_checked_in(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $id = $this->appointmentIn($schedule, $this->today('23:30'));
        $this->travelTo($this->today('00:10')->addDay());
        $this->actingIn($clinic, self::TRANSITION_PERMISSIONS);

        $this->assertErrorCode($this->transition($id, 'check-in'), 422, 'APPOINTMENT_CHECK_IN_NOT_TODAY');
    }

    private function veterinarianUserOf(\App\Models\Schedule $schedule): \App\Models\User
    {
        return \App\Support\CurrentClinic::run($schedule->clinic, fn () => \App\Models\Veterinarian::findOrFail($schedule->veterinarian_id)->user);
    }
}
