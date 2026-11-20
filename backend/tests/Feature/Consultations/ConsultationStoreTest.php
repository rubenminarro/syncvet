<?php

namespace Tests\Feature\Consultations;

use App\Enums\AppointmentStatus;
use App\Enums\AuditEvent;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithConsultations;
use Tests\TestCase;

/**
 * Abrir una consulta (FASE 4, entrega 4.1): desde un turno fichado (pasa a atendido, con su
 * auditoría), desde uno ya atendido el mismo día (el turno no cambia; otro día, 422) o sin turno;
 * el turno con consulta (409), las reglas de attend (403 APPOINTMENT_NOT_YOURS), el paciente
 * fallecido (422 PATIENT_DECEASED), sin sucursal (422 CONSULTATION_NO_BRANCH), el veterinario, el
 * dueño actual, el código CON- y consulted_at.
 */
class ConsultationStoreTest extends TestCase
{
    use InteractsWithConsultations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpClinic();
    }

    public function test_opening_from_a_checked_in_appointment_marks_it_attended_in_the_same_transaction(): void
    {
        $appointment = $this->checkedIn();
        $user = $this->actAsVet();

        $response = $this->openConsultation(['appointment_id' => $appointment, 'temperature_c' => 38.5])->assertCreated();

        $response
            ->assertJsonPath('data.code', 'CON-000001')
            ->assertJsonPath('data.status.value', 'open')
            ->assertJsonPath('data.appointment.id', $appointment)
            ->assertJsonPath('data.appointment.status.value', 'attended')
            ->assertJsonPath('data.patient.id', $this->patientIdOf($appointment))
            ->assertJsonPath('data.branch.id', $this->branch->id)
            ->assertJsonPath('data.veterinarian.id', $this->vet->id)
            ->assertJsonPath('data.reason', 'Control anual')
            ->assertJsonPath('data.temperature_c', '38.5')
            ->assertJsonPath('data.consulted_at', '2026-11-10T12:00:00.000000Z')
            ->assertJsonPath('data.created_by.id', $user->id);

        $row = $this->appointmentRow($appointment);
        $this->assertSame('attended', $row->status);
        $this->assertSame('2026-11-10 09:00:00', $row->attended_at);

        // La auditoría del turno: el cambio a atendido, con quien abrió la consulta.
        $audit = DB::table('audit_logs')
            ->where('auditable_type', 'appointment')
            ->where('auditable_id', $appointment)
            ->where('event', AuditEvent::UPDATED->value)
            ->latest('id')->first();
        $this->assertSame($user->id, (int) $audit->user_id);
        $this->assertSame('attended', json_decode($audit->new_values, true)['status']);
    }

    public function test_the_client_is_the_current_owner_of_the_patient(): void
    {
        $appointment = $this->checkedIn();
        $patientId = $this->patientIdOf($appointment);
        $newOwner = $this->clientIn($this->clinic);
        DB::table('patients')->where('id', $patientId)->update(['client_id' => $newOwner->id]);
        $this->actAsVet();

        $id = $this->opened(['appointment_id' => $appointment]);

        $this->assertSame($newOwner->id, (int) $this->consultationRow($id)->client_id);
    }

    public function test_an_attended_appointment_opens_the_same_day_without_changing(): void
    {
        $appointment = $this->checkedIn(status: AppointmentStatus::ATTENDED);
        $before = $this->appointmentRow($appointment);
        $this->actAsVet();

        $this->opened(['appointment_id' => $appointment]);

        $after = $this->appointmentRow($appointment);
        $this->assertSame('attended', $after->status);
        $this->assertSame($before->attended_at, $after->attended_at);
        $this->assertSame($before->updated_at, $after->updated_at);
    }

    public function test_an_appointment_attended_another_day_is_rejected(): void
    {
        $appointment = $this->checkedIn(status: AppointmentStatus::ATTENDED, startsAt: $this->today('08:30')->subDay());
        $this->actAsVet();

        $this->assertErrorCode($this->openConsultation(['appointment_id' => $appointment]), 422, 'CONSULTATION_APPOINTMENT_NOT_TODAY');
        $this->assertSame(0, DB::table('consultations')->count());
    }

    public function test_an_appointment_that_is_not_checked_in_or_attended_is_rejected(): void
    {
        $this->actAsVet();

        foreach ([AppointmentStatus::PENDING_CONFIRMATION, AppointmentStatus::CONFIRMED, AppointmentStatus::NO_SHOW, AppointmentStatus::CANCELLED] as $status) {
            $appointment = $this->checkedIn(status: $status);

            $this->assertErrorCode($this->openConsultation(['appointment_id' => $appointment]), 422, 'APPOINTMENT_INVALID_TRANSITION');
            $this->assertSame($status->value, $this->appointmentRow($appointment)->status);
        }

        $this->assertSame(0, DB::table('consultations')->count());
    }

    public function test_an_appointment_with_a_consultation_answers_409(): void
    {
        $appointment = $this->checkedIn();
        $this->actAsVet();
        $this->opened(['appointment_id' => $appointment]);

        $this->assertErrorCode($this->openConsultation(['appointment_id' => $appointment]), 409, 'CONSULTATION_ALREADY_EXISTS');
        $this->assertSame(1, DB::table('consultations')->count());
    }

    /**
     * La segunda guarda: si la revisión no la ve (una carrera), el índice único responde 409, no 500.
     */
    public function test_the_unique_index_violation_answers_409(): void
    {
        $appointment = $this->checkedIn();
        $this->actAsVet();
        $this->opened(['appointment_id' => $appointment]);
        // La revisión no ve la consulta (otra clínica en el scope), pero el índice sí.
        DB::table('consultations')->update(['clinic_id' => $this->createClinic()->id]);
        DB::table('appointments')->where('id', $appointment)->update(['status' => 'checked_in']);

        $this->assertErrorCode($this->openConsultation(['appointment_id' => $appointment]), 409, 'CONSULTATION_ALREADY_EXISTS');
    }

    public function test_another_veterinarian_cannot_open_from_an_appointment_that_is_not_theirs(): void
    {
        $appointment = $this->checkedIn();
        $this->actAsVet($this->veterinarianIn($this->branch));

        $this->assertErrorCode($this->openConsultation(['appointment_id' => $appointment]), 403, 'APPOINTMENT_NOT_YOURS');
        $this->assertSame('checked_in', $this->appointmentRow($appointment)->status);
    }

    public function test_an_appointment_of_a_schedule_without_veterinarian_takes_the_ficha_of_who_opens(): void
    {
        $schedule = $this->openSchedule($this->branch, ['veterinarian_id' => null]);
        $appointment = $this->checkedIn($schedule);
        $other = $this->veterinarianIn($this->branch);
        $this->actAsVet($other);

        $this->openConsultation(['appointment_id' => $appointment])->assertCreated()->assertJsonPath('data.veterinarian.id', $other->id);
    }

    public function test_the_administrator_without_ficha_opens_with_the_veterinarian_of_the_appointment(): void
    {
        $appointment = $this->checkedIn();
        $this->actAsAdmin();

        $this->openConsultation(['appointment_id' => $appointment])->assertCreated()->assertJsonPath('data.veterinarian.id', $this->vet->id);
    }

    public function test_a_different_veterinarian_in_the_request_is_rejected(): void
    {
        $appointment = $this->checkedIn();
        $this->actAsAdmin();

        $response = $this->openConsultation(['appointment_id' => $appointment, 'veterinarian_id' => $this->veterinarianIn($this->branch)->id]);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonPath('errors.veterinarian_id.0', 'El veterinario de la consulta es el del turno o el de tu ficha: no se puede elegir otro.');
    }

    public function test_a_deceased_patient_gets_no_new_consultation(): void
    {
        $appointment = $this->checkedIn();
        DB::table('patients')->where('id', $this->patientIdOf($appointment))->update(['deceased_at' => now()]);
        $this->actAsVet();

        $this->assertErrorCode($this->openConsultation(['appointment_id' => $appointment]), 422, 'PATIENT_DECEASED');
        $this->assertSame('checked_in', $this->appointmentRow($appointment)->status);

        $this->assertErrorCode($this->openConsultation(['patient_id' => $this->patientIdOf($appointment)]), 422, 'PATIENT_DECEASED');
        $this->assertSame(0, DB::table('consultations')->count());
    }

    public function test_without_appointment_it_uses_the_current_branch_and_creates_no_appointment(): void
    {
        $patient = $this->patientOf($this->clientIn($this->clinic));
        $appointments = DB::table('appointments')->count();
        $this->actAsVet();

        $this->openConsultation(['patient_id' => $patient->id, 'reason' => 'Urgencia: cojera'])
            ->assertCreated()
            ->assertJsonPath('data.appointment', null)
            ->assertJsonPath('data.branch.id', $this->branch->id)
            ->assertJsonPath('data.client.id', $patient->client_id)
            ->assertJsonPath('data.veterinarian.id', $this->vet->id)
            ->assertJsonPath('data.reason', 'Urgencia: cojera');

        $this->assertSame($appointments, DB::table('appointments')->count());
    }

    public function test_without_appointment_and_without_current_branch_answers_422(): void
    {
        $patient = $this->patientOf($this->clientIn($this->clinic));
        $user = $this->actAsVet();
        $user->forceFill(['current_branch_id' => null])->save();

        $this->assertErrorCode($this->openConsultation(['patient_id' => $patient->id]), 422, 'CONSULTATION_NO_BRANCH');
    }

    public function test_without_ficha_the_veterinarian_is_required_and_must_be_active(): void
    {
        $patient = $this->patientOf($this->clientIn($this->clinic));
        $this->actAsAdmin();

        $response = $this->openConsultation(['patient_id' => $patient->id]);
        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonPath('errors.veterinarian_id.0', 'Indicá el veterinario de la consulta: no tenés una ficha de veterinario.');

        DB::table('veterinarians')->where('id', $this->vet->id)->update(['status' => 'inactive']);
        $response = $this->openConsultation(['patient_id' => $patient->id, 'veterinarian_id' => $this->vet->id]);
        $response->assertJsonPath('errors.veterinarian_id.0', 'El veterinario está inactivo.');

        DB::table('veterinarians')->where('id', $this->vet->id)->update(['status' => 'active']);
        $this->openConsultation(['patient_id' => $patient->id, 'veterinarian_id' => $this->vet->id])
            ->assertCreated()->assertJsonPath('data.veterinarian.id', $this->vet->id);
    }

    public function test_appointment_or_patient_one_of_the_two(): void
    {
        $appointment = $this->checkedIn();
        $this->actAsVet();

        $this->openConsultation([])->assertUnprocessable()->assertJsonValidationErrors(['appointment_id', 'patient_id']);
        $this->openConsultation(['appointment_id' => $appointment, 'patient_id' => $this->patientIdOf($appointment)])
            ->assertUnprocessable()->assertJsonValidationErrors(['appointment_id']);
    }

    public function test_references_of_another_clinic_are_not_found(): void
    {
        $foreign = $this->createClinic();
        $foreignPatient = $this->patientOf($this->clientIn($foreign));
        $foreignAppointment = $this->appointmentFor($foreignPatient);
        $this->actAsVet();

        $this->openConsultation(['patient_id' => $foreignPatient->id])->assertUnprocessable()->assertJsonValidationErrors(['patient_id']);
        $this->openConsultation(['appointment_id' => $foreignAppointment])->assertUnprocessable()->assertJsonValidationErrors(['appointment_id']);
        $this->assertSame(0, DB::table('consultations')->count());
    }

    public function test_consulted_at_is_not_future_nor_before_the_check_in(): void
    {
        $appointment = $this->checkedIn();
        $this->actAsVet();

        $this->openConsultation(['appointment_id' => $appointment, 'consulted_at' => '2026-11-10T09:01:00'])
            ->assertUnprocessable()->assertJsonPath('errors.consulted_at.0', 'La fecha de la consulta no puede ser futura.');
        // Fichado a las 08:40.
        $this->openConsultation(['appointment_id' => $appointment, 'consulted_at' => '2026-11-10T08:39:00'])
            ->assertUnprocessable()->assertJsonPath('errors.consulted_at.0', 'La fecha de la consulta no puede ser anterior al fichado del turno.');
        $this->assertSame('checked_in', $this->appointmentRow($appointment)->status);

        $this->openConsultation(['appointment_id' => $appointment, 'consulted_at' => '2026-11-10T11:40:00Z'])
            ->assertCreated()->assertJsonPath('data.consulted_at', '2026-11-10T11:40:00.000000Z');
    }

    public function test_the_reception_cannot_open(): void
    {
        $appointment = $this->checkedIn();
        $this->actAsReceptionist();

        $this->assertErrorCode($this->openConsultation(['appointment_id' => $appointment]), 403, 'FORBIDDEN');
    }

    public function test_codes_follow_the_series(): void
    {
        $this->actAsVet();

        $first = $this->openConsultation(['patient_id' => $this->patientOf($this->clientIn($this->clinic))->id])->json('data.code');
        $second = $this->openConsultation(['patient_id' => $this->patientOf($this->clientIn($this->clinic))->id])->json('data.code');

        $this->assertSame(['CON-000001', 'CON-000002'], [$first, $second]);
    }

    public function test_the_appointment_detail_shows_the_consultation(): void
    {
        $appointment = $this->checkedIn();
        $this->actAsVet();
        $id = $this->opened(['appointment_id' => $appointment]);
        $this->actAsAdmin();

        $this->getJson("/api/appointments/{$appointment}")
            ->assertOk()
            ->assertJsonPath('data.consultation', ['id' => $id, 'code' => 'CON-000001', 'status' => ['value' => 'open', 'label' => 'Abierta']]);

        $other = $this->checkedIn();
        $this->getJson("/api/appointments/{$other}")->assertOk()->assertJsonPath('data.consultation', null);
    }

    public function test_the_appointment_list_and_the_queue_say_if_it_has_a_consultation(): void
    {
        $with = $this->checkedIn();
        $without = $this->checkedIn(startsAt: $this->today('09:00'));
        $this->actAsVet();
        $this->opened(['appointment_id' => $with]);
        $this->actAsAdmin();

        $list = collect($this->getJson('/api/appointments')->assertOk()->json('data'))->pluck('has_consultation', 'id');
        $this->assertSame([$with => true, $without => false], $list->only([$with, $without])->all());

        $queue = collect($this->getJson('/api/appointments/queue')->assertOk()->json('data'))->pluck('has_consultation', 'id');
        $this->assertSame([$with => true, $without => false], $queue->only([$with, $without])->all());
    }
}
