<?php

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentStatus;
use App\Http\Requests\IndexAppointmentRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Listado de turnos (FASE 3, entrega 3.2a): por defecto los de hoy (Asunción); from y to,
 * hasta 31 días; filtros; search literal en el paciente, el cliente y el contacto; nada de
 * otra clínica.
 */
class AppointmentIndexTest extends TestCase
{
    use InteractsWithAppointments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeToday();
    }

    private function ids(string $uri): array
    {
        return array_column($this->getJson($uri)->assertOk()->json('data'), 'id');
    }

    public function test_by_default_the_appointments_of_today(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);
        $late = $this->booked($schedule, ['starts_at' => $this->at('19:30', self::TODAY)]);
        $early = $this->booked($schedule, ['starts_at' => $this->at('08:00', self::TODAY)]);
        $monday = $this->booked($schedule);

        $this->assertSame([$early, $late], $this->ids('/api/appointments'));
        $this->assertSame([$monday], $this->ids('/api/appointments?from=' . self::MONDAY));
        $this->assertSame([$early, $late, $monday], $this->ids('/api/appointments?from=' . self::TODAY . '&to=' . self::MONDAY));
        // Con hora y zona: desde las 11:30 UTC (08:30 en Asunción) de hoy.
        $this->assertSame([$late], $this->ids('/api/appointments?from=' . self::TODAY . 'T11:30:00Z&to=' . self::TODAY));

        $this->getJson('/api/appointments')
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('data.0.status.value', 'pending_confirmation');
    }

    public function test_the_range_is_validated(): void
    {
        $clinic = $this->createClinic();
        $this->actAsReception($clinic);

        $this->getJson('/api/appointments?from=2026-11-01&to=2026-12-02')
            ->assertUnprocessable()->assertJsonPath('errors.to.0', IndexAppointmentRequest::RANGE_TOO_LONG);
        $this->getJson('/api/appointments?from=2026-11-01&to=2026-12-01')->assertOk();
        $this->getJson('/api/appointments?from=2026-11-10&to=2026-11-09')->assertUnprocessable()->assertJsonValidationErrors(['to']);
        $this->getJson('/api/appointments?from=mañana')->assertUnprocessable()->assertJsonValidationErrors(['from']);
        $this->getJson('/api/appointments?status=online&series_id=x&schedule_id=999999')
            ->assertUnprocessable()->assertJsonValidationErrors(['status.0', 'series_id', 'schedule_id']);
    }

    public function test_the_filters(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $schedule = $this->openSchedule($branch);
        $other = $this->openSchedule($branch);
        $patient = $this->patientOf($this->clientIn($clinic));
        $this->actAsReception($clinic);
        $withPatient = $this->booked($schedule, ['patient_id' => $patient->id, 'contact_name' => null, 'contact_phone' => null]);
        $inOther = $this->booked($other);
        $cancelled = $this->appointmentIn($schedule, CarbonImmutable::parse(self::MONDAY . ' 15:00', 'America/Asuncion'), AppointmentStatus::CANCELLED);
        $base = '/api/appointments?from=' . self::MONDAY;

        $this->assertSame([$withPatient, $cancelled], $this->ids("{$base}&schedule_id={$schedule->id}"));
        $this->assertSame([$inOther], $this->ids("{$base}&veterinarian_id={$other->veterinarian_id}"));
        $this->assertSame([$withPatient], $this->ids("{$base}&patient_id={$patient->id}"));
        $this->assertSame([$withPatient], $this->ids("{$base}&client_id={$patient->client_id}"));
        $this->assertSame([$cancelled], $this->ids("{$base}&status=cancelled"));
        $this->assertSame([$withPatient, $inOther], $this->ids("{$base}&status[]=pending_confirmation&status[]=confirmed"));
        $this->assertSame([$withPatient, $inOther, $cancelled], $this->ids("{$base}&branch_id={$branch->id}"));
    }

    public function test_search_is_literal_on_patient_client_and_contact(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $client = $this->clientIn($clinic, ['document_number' => '99123456', 'first_name' => 'Rocío', 'last_name' => 'Benítez']);
        $patient = $this->patientOf($client, ['name' => 'Firulais']);
        $this->actAsReception($clinic);
        $withPatient = $this->booked($schedule, ['patient_id' => $patient->id, 'contact_name' => null, 'contact_phone' => null]);
        $contact = $this->booked($schedule, ['starts_at' => $this->at('11:00'), 'contact_name' => 'Marta 50% off', 'contact_phone' => '985000111']);
        $base = '/api/appointments?from=' . self::MONDAY . '&search=';

        $this->assertSame([$withPatient], $this->ids($base . 'firu'));
        $this->assertSame([$withPatient], $this->ids($base . $patient->code));
        $this->assertSame([$withPatient], $this->ids($base . '99123'));
        $this->assertSame([$withPatient], $this->ids($base . 'Benítez'));
        $this->assertSame([$contact], $this->ids($base . urlencode('50%')));
        $this->assertSame([$contact], $this->ids($base . '985000'));
        // "%" y "_" no son comodines: "%" solo encuentra el contacto que lo tiene.
        $this->assertSame([$contact], $this->ids($base . urlencode('%')));
        $this->assertSame([], $this->ids($base . '_'));
    }

    public function test_appointments_of_another_clinic_are_not_listed_or_shown(): void
    {
        $foreign = $this->createClinic();
        $foreignSchedule = $this->openSchedule($this->defaultBranchIn($foreign));
        $this->actAsReception($foreign);
        $foreignId = $this->booked($foreignSchedule, ['starts_at' => $this->at('09:00', self::TODAY)]);

        $clinic = $this->createClinic();
        $this->actAsReception($clinic);

        $this->assertSame([], $this->ids('/api/appointments'));
        $this->assertErrorCode($this->getJson("/api/appointments/{$foreignId}"), 404, 'RESOURCE_NOT_FOUND');
        $this->assertErrorCode($this->patchJson("/api/appointments/{$foreignId}", ['notes' => 'x']), 404, 'RESOURCE_NOT_FOUND');
        $this->assertNull(DB::table('appointments')->where('id', $foreignId)->value('notes'));
    }
}
