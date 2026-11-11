<?php

namespace Tests\Feature\Appointments;

use App\Enums\AuditEvent;
use App\Enums\ServiceStatus;
use App\Enums\VeterinarianStatus;
use App\Http\Requests\AppointmentMessages;
use App\Models\Veterinarian;
use App\Support\CurrentClinic;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Alta de un turno (FASE 3, entrega 3.2a; P4, P5 y preguntas 16 y 21): el paciente, un
 * cliente sin paciente o un contacto; la sucursal y el veterinario salen de la agenda; ends_at
 * por defecto; el día, la grilla y la medianoche; la agenda, el veterinario y el servicio
 * activos. Con un usuario con los permisos de turnos (no el administrador).
 */
class AppointmentStoreTest extends TestCase
{
    use InteractsWithAppointments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeToday();
    }

    public function test_an_appointment_with_a_patient_takes_its_owner_the_branch_and_the_veterinarian(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $schedule = $this->openSchedule($branch);
        $patient = $this->patientOf($this->clientIn($clinic));
        $user = $this->actAsReception($clinic);

        $response = $this->book($schedule, [
            'patient_id' => $patient->id,
            'contact_name' => null,
            'contact_phone' => null,
            'reason' => 'Control anual',
        ])->assertCreated()
            ->assertJsonPath('data.patient.id', $patient->id)
            ->assertJsonPath('data.client.id', $patient->client_id)
            ->assertJsonPath('data.branch.id', $branch->id)
            ->assertJsonPath('data.veterinarian.id', $schedule->veterinarian_id)
            ->assertJsonPath('data.schedule.id', $schedule->id)
            ->assertJsonPath('data.service', null)
            ->assertJsonPath('data.contact_name', null)
            // Las 10:00 de Asunción (UTC-3) son las 13:00 UTC; sin servicio, slot_minutes (30).
            ->assertJsonPath('data.starts_at', '2026-11-16T13:00:00.000000Z')
            ->assertJsonPath('data.ends_at', '2026-11-16T13:30:00.000000Z')
            ->assertJsonPath('data.status.value', 'pending_confirmation')
            ->assertJsonPath('data.status.label', 'A confirmar')
            ->assertJsonPath('data.source.value', 'reception')
            ->assertJsonPath('data.is_overbooked', false)
            ->assertJsonPath('data.series_id', null)
            ->assertJsonPath('data.reason', 'Control anual')
            ->assertJsonPath('data.created_by.id', $user->id);

        $row = $this->appointmentRow($response->json('data.id'));
        $this->assertSame('2026-11-16 10:00:00', $row->starts_at);
        $this->assertSame('2026-11-16 10:30:00', $row->ends_at);
        $this->assertSame($clinic->id, (int) $row->clinic_id);
        $this->assertSame(1, DB::table('audit_logs')->where('auditable_type', 'appointment')->where('event', AuditEvent::CREATED->value)->count());
    }

    public function test_an_appointment_with_a_client_and_no_patient(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $client = $this->clientIn($clinic);
        $this->actAsReception($clinic);

        $this->book($schedule, ['client_id' => $client->id, 'contact_name' => null, 'contact_phone' => null])
            ->assertCreated()
            ->assertJsonPath('data.patient', null)
            ->assertJsonPath('data.client.id', $client->id)
            ->assertJsonPath('data.client.display_name', $client->display_name);
    }

    public function test_an_appointment_with_a_contact(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);

        $this->book($schedule, ['source' => 'whatsapp'])
            ->assertCreated()
            ->assertJsonPath('data.patient', null)
            ->assertJsonPath('data.client', null)
            ->assertJsonPath('data.contact_name', 'Juan, el de la perra Luna')
            ->assertJsonPath('data.contact_phone', '981123456')
            ->assertJsonPath('data.source.label', 'WhatsApp');
    }

    public function test_without_patient_client_or_contact_it_is_rejected(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);

        $this->book($schedule, ['contact_name' => null, 'contact_phone' => null])
            ->assertUnprocessable()
            ->assertJsonPath('errors.patient_id.0', AppointmentMessages::WHO_REQUIRED);

        $this->book($schedule, ['contact_phone' => null])
            ->assertUnprocessable()
            ->assertJsonPath('errors.contact_phone.0', AppointmentMessages::CONTACT_TOGETHER);

        $this->book($schedule, ['contact_name' => ''])
            ->assertUnprocessable()
            ->assertJsonPath('errors.contact_name.0', AppointmentMessages::CONTACT_TOGETHER);

        $this->assertSame(0, DB::table('appointments')->count());
    }

    public function test_a_patient_of_another_client_is_rejected(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $patient = $this->patientOf($this->clientIn($clinic));
        $other = $this->clientIn($clinic);
        $this->actAsReception($clinic);

        $this->book($schedule, ['patient_id' => $patient->id, 'client_id' => $other->id, 'contact_name' => null, 'contact_phone' => null])
            ->assertUnprocessable()
            ->assertJsonPath('errors.patient_id.0', AppointmentMessages::PATIENT_NOT_OF_CLIENT);

        // Su dueño, explícito, pasa.
        $this->book($schedule, ['patient_id' => $patient->id, 'client_id' => $patient->client_id, 'contact_name' => null, 'contact_phone' => null])
            ->assertCreated();
    }

    public function test_a_deceased_patient_is_rejected(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $patient = $this->patientOf($this->clientIn($clinic), ['deceased_at' => '2026-11-01T10:00:00Z']);
        $this->actAsReception($clinic);

        $this->book($schedule, ['patient_id' => $patient->id, 'contact_name' => null, 'contact_phone' => null])
            ->assertUnprocessable()
            ->assertJsonPath('errors.patient_id.0', AppointmentMessages::PATIENT_DECEASED);
    }

    public function test_references_of_another_clinic_are_rejected(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $foreign = $this->createClinic();
        $foreignPatient = $this->patientOf($this->clientIn($foreign));
        $foreignSchedule = $this->openSchedule($this->defaultBranchIn($foreign));
        $foreignService = $this->serviceIn($foreign);
        $this->actAsReception($clinic);

        $this->book($schedule, ['patient_id' => $foreignPatient->id, 'contact_name' => null, 'contact_phone' => null])
            ->assertUnprocessable()->assertJsonPath('errors.patient_id.0', AppointmentMessages::PATIENT_NOT_AVAILABLE);
        $this->book($schedule, ['client_id' => $foreignPatient->client_id, 'contact_name' => null, 'contact_phone' => null])
            ->assertUnprocessable()->assertJsonPath('errors.client_id.0', AppointmentMessages::CLIENT_NOT_AVAILABLE);
        $this->book($foreignSchedule)
            ->assertUnprocessable()->assertJsonPath('errors.schedule_id.0', AppointmentMessages::SCHEDULE_NOT_AVAILABLE);
        $this->book($schedule, ['service_id' => $foreignService->id])
            ->assertUnprocessable()->assertJsonPath('errors.service_id.0', AppointmentMessages::SERVICE_NOT_AVAILABLE);

        $this->assertSame(0, DB::table('appointments')->count());
    }

    public function test_contact_data_with_a_client_or_a_patient_is_rejected(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $patient = $this->patientOf($this->clientIn($clinic));
        $this->actAsReception($clinic);

        $this->book($schedule, ['client_id' => $patient->client_id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.contact_name.0', AppointmentMessages::CONTACT_PROHIBITED);

        $this->book($schedule, ['patient_id' => $patient->id, 'contact_name' => null])
            ->assertUnprocessable()
            ->assertJsonPath('errors.contact_phone.0', AppointmentMessages::CONTACT_PROHIBITED);
    }

    public function test_ends_at_defaults_to_the_service_duration(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $default = $this->serviceIn($clinic, ['name' => 'Peluquería', 'duration_minutes' => 90]);
        $sent = $this->serviceIn($clinic, ['name' => 'Vacunación', 'duration_minutes' => 15]);
        $schedule = $this->openSchedule($branch, ['service_id' => $default->id, 'slot_minutes' => 20]);
        $this->actAsReception($clinic);

        // El servicio de la agenda.
        $this->book($schedule)
            ->assertCreated()
            ->assertJsonPath('data.service.id', $default->id)
            ->assertJsonPath('data.ends_at', '2026-11-16T14:30:00.000000Z');

        // El del pedido.
        $this->book($schedule, ['starts_at' => $this->at('12:00'), 'service_id' => $sent->id])
            ->assertCreated()
            ->assertJsonPath('data.service.id', $sent->id)
            ->assertJsonPath('data.ends_at', '2026-11-16T15:15:00.000000Z');

        // ends_at explícito manda.
        $this->book($schedule, ['starts_at' => $this->at('13:00'), 'ends_at' => $this->at('13:10')])
            ->assertCreated()
            ->assertJsonPath('data.ends_at', '2026-11-16T16:10:00.000000Z');
    }

    public function test_the_instant_is_the_same_with_or_without_a_zone(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);

        foreach (['2026-11-16T13:00:00Z', '2026-11-16T10:00:00-03:00', '2026-11-16T10:00:00', '2026-11-16 10:00'] as $index => $startsAt) {
            $id = $this->booked($schedule, ['starts_at' => $startsAt, 'contact_name' => "Contacto {$index}"]);

            $this->assertSame('2026-11-16 10:00:00', $this->appointmentRow($id)->starts_at, $startsAt);
            // Cancelado: libera el horario para el siguiente.
            DB::table('appointments')->where('id', $id)->update(['status' => 'cancelled']);
        }
    }

    public static function fieldsSetByTheSystem(): array
    {
        return [
            'branch_id' => ['branch_id', 1],
            'veterinarian_id' => ['veterinarian_id', 1],
            'status' => ['status', 'confirmed'],
            'series_id' => ['series_id', '9b2f1c4e-7f1a-4c55-a9a3-1f1e2d3c4b5a'],
            'is_overbooked' => ['is_overbooked', true],
        ];
    }

    #[DataProvider('fieldsSetByTheSystem')]
    public function test_fields_set_by_the_system_are_prohibited(string $field, mixed $value): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);

        $this->book($schedule, [$field => $value])->assertUnprocessable()->assertJsonValidationErrors([$field]);

        // null se acepta (el frontend manda el formulario completo).
        $this->book($schedule, [$field => null])->assertCreated();
    }

    public function test_the_online_source_is_rejected(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);

        $this->book($schedule, ['source' => 'online'])
            ->assertUnprocessable()
            // Con las etiquetas en español, no los valores internos (revisión de la 3.2a, desvío 13).
            ->assertJsonPath('errors.source.0', 'El origen debe ser recepción, teléfono o WhatsApp: la reserva online no está disponible.');

        $this->book($schedule, ['source' => 'phone'])->assertCreated()->assertJsonPath('data.source.label', 'Teléfono');
    }

    /**
     * Pregunta 16: desde el comienzo del día de hoy en Asunción.
     */
    public function test_a_past_day_is_rejected_but_earlier_today_is_accepted(): void
    {
        $this->freezeToday('15:00:00');
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);

        $this->book($schedule, ['starts_at' => $this->at('19:30', '2026-11-09')])
            ->assertUnprocessable()
            ->assertJsonPath('errors.starts_at.0', AppointmentMessages::PAST_DAY);

        // 23:30 UTC del 9 son las 20:30 del 9 en Asunción: también es ayer.
        $this->book($schedule, ['starts_at' => '2026-11-09T23:30:00Z'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.starts_at.0', AppointmentMessages::PAST_DAY);

        $this->book($schedule, ['starts_at' => $this->at('08:00', self::TODAY)])->assertCreated();
    }

    /**
     * Revisión de la 3.2a (hallazgo 3): a las 22:30 de Asunción ya es el día siguiente en UTC
     * (01:30 del 11), pero "hoy" sigue siendo el martes 10 de Asunción. Un turno de hoy a las
     * 20:00 entra; uno del lunes 9 a las 21:30 (00:30 UTC del 10), no.
     */
    public function test_near_midnight_today_is_still_the_day_of_asuncion(): void
    {
        $this->freezeToday('22:30:00');
        $this->assertSame('2026-11-11 01:30', now()->utc()->format('Y-m-d H:i'));
        $clinic = $this->createClinic();
        $schedule = $this->scheduleIn($this->defaultBranchIn($clinic), ['veterinarian_id' => null, 'service_id' => $this->serviceIn($clinic)->id]);
        $this->hoursFor($schedule, [[1, '08:00', '23:00'], [2, '08:00', '23:00']]);
        $this->actAsReception($clinic);

        $this->book($schedule, ['starts_at' => $this->at('20:00', self::TODAY), 'ends_at' => $this->at('20:30', self::TODAY)])->assertCreated();

        $this->book($schedule, ['starts_at' => '2026-11-10T00:30:00Z', 'ends_at' => '2026-11-10T01:00:00Z'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.starts_at.0', AppointmentMessages::PAST_DAY);
    }

    public static function badTimes(): array
    {
        return [
            'minutos fuera de la grilla' => [['starts_at' => '2026-11-16T10:03:00'], 'starts_at', AppointmentMessages::OFF_GRID],
            'con segundos' => [['starts_at' => '2026-11-16T10:00:30'], 'starts_at', AppointmentMessages::OFF_GRID],
            'fin fuera de la grilla' => [['ends_at' => '2026-11-16T10:31:00'], 'ends_at', AppointmentMessages::OFF_GRID],
            'fin antes del inicio' => [['ends_at' => '2026-11-16T09:30:00'], 'ends_at', AppointmentMessages::ENDS_BEFORE_START],
            'fin igual al inicio' => [['ends_at' => '2026-11-16T10:00:00'], 'ends_at', AppointmentMessages::ENDS_BEFORE_START],
            'cruza la medianoche' => [['starts_at' => '2026-11-16T19:00:00', 'ends_at' => '2026-11-17T01:00:00'], 'ends_at', AppointmentMessages::CROSSES_MIDNIGHT],
            'más de 720 minutos' => [['starts_at' => '2026-11-16T08:00:00', 'ends_at' => '2026-11-16T20:05:00'], 'ends_at', AppointmentMessages::TOO_LONG],
            'fecha sola' => [['starts_at' => '2026-11-16'], 'starts_at', AppointmentMessages::DATE_TIME_FORMAT],
            'texto' => [['starts_at' => 'mañana'], 'starts_at', AppointmentMessages::DATE_TIME_FORMAT],
        ];
    }

    #[DataProvider('badTimes')]
    public function test_bad_times_are_rejected(array $times, string $field, string $message): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);

        $this->book($schedule, $times)->assertUnprocessable()->assertJsonPath("errors.{$field}.0", $message);
    }

    public function test_exactly_720_minutes_is_accepted(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);

        $this->book($schedule, ['starts_at' => $this->at('08:00'), 'ends_at' => $this->at('20:00')])->assertCreated();
    }

    public function test_an_inactive_schedule_does_not_take_appointments(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic), ['is_active' => false]);
        $this->actAsReception($clinic);

        $this->book($schedule)->assertUnprocessable()->assertJsonPath('errors.schedule_id.0', AppointmentMessages::SCHEDULE_NOT_AVAILABLE);
    }

    public function test_a_schedule_with_an_inactive_veterinarian_does_not_take_appointments(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);
        DB::table('veterinarians')->where('id', $schedule->veterinarian_id)->update(['status' => VeterinarianStatus::INACTIVE->value]);

        $this->book($schedule)->assertUnprocessable()->assertJsonPath('errors.schedule_id.0', AppointmentMessages::VETERINARIAN_INACTIVE);

        // Con la ficha activa y el usuario inactivo, tampoco.
        DB::table('veterinarians')->where('id', $schedule->veterinarian_id)->update(['status' => VeterinarianStatus::ACTIVE->value]);
        $userId = CurrentClinic::run($clinic, fn () => Veterinarian::findOrFail($schedule->veterinarian_id)->user_id);
        DB::table('users')->where('id', $userId)->update(['is_active' => false]);

        $this->book($schedule)->assertUnprocessable()->assertJsonPath('errors.schedule_id.0', AppointmentMessages::VETERINARIAN_INACTIVE);
    }

    public function test_an_inactive_service_does_not_take_appointments(): void
    {
        $clinic = $this->createClinic();
        $default = $this->serviceIn($clinic, ['name' => 'Peluquería']);
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic), ['service_id' => $default->id]);
        $inactive = $this->serviceIn($clinic, ['name' => 'Vacunación', 'status' => ServiceStatus::INACTIVE]);
        $this->actAsReception($clinic);

        $this->book($schedule, ['service_id' => $inactive->id])
            ->assertUnprocessable()->assertJsonPath('errors.service_id.0', AppointmentMessages::SERVICE_NOT_AVAILABLE);

        // El servicio de la agenda, inactivado después de armarla.
        DB::table('services')->where('id', $default->id)->update(['status' => ServiceStatus::INACTIVE->value]);

        $this->book($schedule)->assertUnprocessable()->assertJsonPath('errors.service_id.0', AppointmentMessages::SERVICE_NOT_AVAILABLE);
    }

    public function test_a_schedule_without_a_veterinarian_takes_appointments(): void
    {
        $clinic = $this->createClinic();
        $service = $this->serviceIn($clinic, ['name' => 'Peluquería']);
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic), ['veterinarian_id' => null, 'service_id' => $service->id]);
        $this->actAsReception($clinic);

        $this->book($schedule)->assertCreated()->assertJsonPath('data.veterinarian', null);
    }

    public function test_the_veterinarian_role_cannot_book(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        Sanctum::actingAs($this->userWithRoles($clinic, ['veterinario']));

        $this->assertErrorCode($this->book($schedule), 403, 'FORBIDDEN');
    }

    public function test_the_reception_role_books_and_shows(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        Sanctum::actingAs($this->userWithRoles($clinic, ['recepcionista']));

        $id = $this->booked($schedule);

        $this->getJson("/api/appointments/{$id}")->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.notes', null);
    }
}
