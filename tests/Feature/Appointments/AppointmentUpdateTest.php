<?php

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentStatus;
use App\Http\Requests\AppointmentMessages;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Edición y mover un turno (FASE 3, entrega 3.2a; P7 y pregunta 17): solo a confirmar o
 * confirmado; mover revisa otra vez el horario, los bloqueos y la superposición sin contarse
 * a sí mismo, copia la sucursal y el veterinario de la agenda de destino y conserva el estado.
 */
class AppointmentUpdateTest extends TestCase
{
    use InteractsWithAppointments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeToday();
    }

    private function monday(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse(self::MONDAY . " {$time}", 'America/Asuncion');
    }

    public function test_moving_within_the_schedule_does_not_collide_with_itself_and_keeps_its_status(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $id = $this->appointmentIn($schedule, $this->monday('10:00'), AppointmentStatus::CONFIRMED);
        $this->actAsReception($clinic);

        $this->patchJson("/api/appointments/{$id}", ['starts_at' => $this->at('10:15')])
            ->assertOk()
            ->assertJsonPath('data.status.value', 'confirmed')
            // Sin ends_at, conserva la duración (30 minutos).
            ->assertJsonPath('data.starts_at', '2026-11-16T13:15:00.000000Z')
            ->assertJsonPath('data.ends_at', '2026-11-16T13:45:00.000000Z');
    }

    public function test_moving_to_another_schedule_copies_its_branch_and_veterinarian(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $other = $this->branchIn($clinic);
        $schedule = $this->openSchedule($branch);
        $target = $this->openSchedule($other);
        $this->actAsReception($clinic);
        $id = $this->booked($schedule);

        $this->patchJson("/api/appointments/{$id}", ['schedule_id' => $target->id])
            ->assertOk()
            ->assertJsonPath('data.schedule.id', $target->id)
            ->assertJsonPath('data.branch.id', $other->id)
            ->assertJsonPath('data.veterinarian.id', $target->veterinarian_id)
            ->assertJsonPath('data.starts_at', '2026-11-16T13:00:00.000000Z');
    }

    public function test_moving_to_a_taken_slot_is_rejected(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic, overbook: true);
        $this->booked($schedule);
        $id = $this->booked($schedule, ['starts_at' => $this->at('11:00')]);

        $this->assertErrorCode($this->patchJson("/api/appointments/{$id}", ['starts_at' => $this->at('10:15')]), 409, 'APPOINTMENT_OVERLAP');
        $this->assertSame('2026-11-16 11:00:00', $this->appointmentRow($id)->starts_at);

        // Como sobreturno, sí; y al moverlo a un horario libre deja de serlo.
        $this->patchJson("/api/appointments/{$id}", ['starts_at' => $this->at('10:15'), 'overbook' => true])
            ->assertOk()->assertJsonPath('data.is_overbooked', true);
        $this->patchJson("/api/appointments/{$id}", ['starts_at' => $this->at('15:00')])
            ->assertOk()->assertJsonPath('data.is_overbooked', false);
    }

    public function test_moving_respects_the_hours_the_blocks_and_the_day(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->blockFor($schedule, ['starts_at' => $this->monday('12:00'), 'ends_at' => $this->monday('13:00')]);
        $this->actAsReception($clinic);
        $id = $this->booked($schedule);

        $this->assertErrorCode($this->patchJson("/api/appointments/{$id}", ['starts_at' => $this->at('07:00')]), 422, 'APPOINTMENT_OUTSIDE_HOURS');
        $this->assertErrorCode($this->patchJson("/api/appointments/{$id}", ['starts_at' => $this->at('12:15')]), 422, 'APPOINTMENT_IN_BLOCK');
        $this->patchJson("/api/appointments/{$id}", ['starts_at' => $this->at('10:00', '2026-11-09')])
            ->assertUnprocessable()->assertJsonPath('errors.starts_at.0', AppointmentMessages::PAST_DAY);
        $this->patchJson("/api/appointments/{$id}", ['ends_at' => $this->at('09:00')])
            ->assertUnprocessable()->assertJsonPath('errors.ends_at.0', AppointmentMessages::ENDS_BEFORE_START);
    }

    public function test_moving_to_an_inactive_schedule_is_rejected_but_other_data_still_changes(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $schedule = $this->openSchedule($branch);
        $inactive = $this->openSchedule($branch, ['is_active' => false]);
        $this->actAsReception($clinic);
        $id = $this->booked($schedule);

        $this->patchJson("/api/appointments/{$id}", ['schedule_id' => $inactive->id])
            ->assertUnprocessable()->assertJsonPath('errors.schedule_id.0', AppointmentMessages::SCHEDULE_NOT_AVAILABLE);

        // Pregunta 24: una agenda inactiva conserva sus turnos, que se siguen editando.
        DB::table('schedules')->where('id', $schedule->id)->update(['is_active' => false]);
        $this->patchJson("/api/appointments/{$id}", ['notes' => 'Avisó que llega tarde', 'reason' => 'Control'])
            ->assertOk()->assertJsonPath('data.notes', 'Avisó que llega tarde');
        // Reenviar el mismo horario no es moverlo.
        $this->patchJson("/api/appointments/{$id}", ['schedule_id' => $schedule->id, 'starts_at' => $this->at('10:00')])->assertOk();
    }

    public static function notEditable(): array
    {
        return [
            'fichado' => [AppointmentStatus::CHECKED_IN],
            'atendido' => [AppointmentStatus::ATTENDED],
            'ausente' => [AppointmentStatus::NO_SHOW],
            'cancelado' => [AppointmentStatus::CANCELLED],
        ];
    }

    #[DataProvider('notEditable')]
    public function test_only_pending_or_confirmed_appointments_are_edited(AppointmentStatus $status): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $id = $this->appointmentIn($schedule, $this->monday('10:00'), $status);
        $this->actAsReception($clinic);

        $response = $this->patchJson("/api/appointments/{$id}", ['starts_at' => $this->at('11:00')]);

        $this->assertErrorCode($response, 422, 'APPOINTMENT_NOT_EDITABLE');
        $response->assertJsonPath('message', 'Solo se modifican los turnos a confirmar o confirmados.');
        $this->assertErrorCode($this->patchJson("/api/appointments/{$id}", ['notes' => 'x']), 422, 'APPOINTMENT_NOT_EDITABLE');
        $this->assertSame('2026-11-16 10:00:00', $this->appointmentRow($id)->starts_at);
    }

    public function test_from_contact_to_client_and_from_client_to_patient(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $patient = $this->patientOf($this->clientIn($clinic));
        $this->actAsReception($clinic);
        $id = $this->booked($schedule);

        $this->patchJson("/api/appointments/{$id}", ['client_id' => $patient->client_id])
            ->assertOk()
            ->assertJsonPath('data.client.id', $patient->client_id)
            ->assertJsonPath('data.contact_name', null)
            ->assertJsonPath('data.contact_phone', null);

        $this->patchJson("/api/appointments/{$id}", ['patient_id' => $patient->id])
            ->assertOk()
            ->assertJsonPath('data.patient.id', $patient->id)
            ->assertJsonPath('data.client.id', $patient->client_id);
    }

    public function test_the_patient_has_to_belong_to_the_client(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $patient = $this->patientOf($this->clientIn($clinic));
        $other = $this->clientIn($clinic);
        $this->actAsReception($clinic);
        $id = $this->booked($schedule, ['patient_id' => $patient->id, 'contact_name' => null, 'contact_phone' => null]);

        $this->patchJson("/api/appointments/{$id}", ['client_id' => $other->id])
            ->assertUnprocessable()->assertJsonPath('errors.patient_id.0', AppointmentMessages::PATIENT_NOT_OF_CLIENT);
        $this->patchJson("/api/appointments/{$id}", ['patient_id' => $patient->id, 'client_id' => $other->id])
            ->assertUnprocessable()->assertJsonPath('errors.patient_id.0', AppointmentMessages::PATIENT_NOT_OF_CLIENT);
        $this->patchJson("/api/appointments/{$id}", ['contact_name' => 'Otra persona'])
            ->assertUnprocessable()->assertJsonPath('errors.contact_name.0', AppointmentMessages::CONTACT_PROHIBITED);
        // Quitar el paciente y el cliente sin contacto deja el turno sin nadie.
        $this->patchJson("/api/appointments/{$id}", ['patient_id' => null, 'client_id' => null])
            ->assertUnprocessable()->assertJsonPath('errors.patient_id.0', AppointmentMessages::WHO_REQUIRED);
    }

    /**
     * El paciente que el turno ya tiene se acepta aunque haya fallecido (el formulario lo
     * reenvía); otro fallecido, no.
     */
    public function test_a_patient_that_died_after_booking_can_be_resent(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $client = $this->clientIn($clinic);
        $patient = $this->patientOf($client);
        $deceased = $this->patientOf($client, ['deceased_at' => '2026-11-01T10:00:00Z']);
        $this->actAsReception($clinic);
        $id = $this->booked($schedule, ['patient_id' => $patient->id, 'contact_name' => null, 'contact_phone' => null]);
        DB::table('patients')->where('id', $patient->id)->update(['deceased_at' => '2026-11-10 06:00:00']);

        $this->patchJson("/api/appointments/{$id}", ['patient_id' => $patient->id, 'notes' => 'x'])->assertOk();
        $this->patchJson("/api/appointments/{$id}", ['patient_id' => $deceased->id])
            ->assertUnprocessable()->assertJsonPath('errors.patient_id.0', AppointmentMessages::PATIENT_DECEASED);
    }

    public function test_changing_only_the_service_keeps_ends_at(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $service = $this->serviceIn($clinic, ['duration_minutes' => 90]);
        $this->actAsReception($clinic);
        $id = $this->booked($schedule);

        $this->patchJson("/api/appointments/{$id}", ['service_id' => $service->id])
            ->assertOk()
            ->assertJsonPath('data.service.id', $service->id)
            ->assertJsonPath('data.ends_at', '2026-11-16T13:30:00.000000Z');
    }

    public function test_overbook_without_the_permission_is_forbidden(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);
        $id = $this->booked($schedule);

        $this->assertErrorCode($this->patchJson("/api/appointments/{$id}", ['overbook' => true]), 403, 'FORBIDDEN');
    }

    public function test_there_is_no_delete_and_the_veterinarian_cannot_edit(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);
        $id = $this->booked($schedule);

        $this->assertErrorCode($this->deleteJson("/api/appointments/{$id}"), 405, 'METHOD_NOT_ALLOWED');

        Sanctum::actingAs($this->userWithRoles($clinic, ['veterinario']));
        $this->assertErrorCode($this->patchJson("/api/appointments/{$id}", ['notes' => 'x']), 403, 'FORBIDDEN');
        $this->getJson("/api/appointments/{$id}")->assertOk();
    }

    public function test_put_is_partial_too(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);
        $id = $this->booked($schedule, ['reason' => 'Vacuna']);

        $this->putJson("/api/appointments/{$id}", ['notes' => 'Trae la libreta'])
            ->assertOk()
            ->assertJsonPath('data.reason', 'Vacuna')
            ->assertJsonPath('data.notes', 'Trae la libreta')
            ->assertJsonPath('data.contact_name', 'Juan, el de la perra Luna');
    }
}
