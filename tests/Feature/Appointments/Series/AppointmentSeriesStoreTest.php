<?php

namespace Tests\Feature\Appointments\Series;

use App\Enums\AuditEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Turnos recurrentes (FASE 3, entrega 3.3; P1): la serie se genera entera al crearla, semanal o
 * cada N semanas, con cantidad (count) o fecha de fin (until), hasta 52 turnos, con el mismo
 * series_id. Las fechas son el primer turno más 7 × every_weeks × i días, a la misma hora de
 * reloj de Asunción y con la misma duración.
 */
class AppointmentSeriesStoreTest extends TestCase
{
    use InteractsWithAppointments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeToday();
    }

    public function test_a_weekly_series_by_count(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);

        $response = $this->bookSeries($schedule, ['count' => 4])
            ->assertCreated()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('message', 'Serie de 4 turnos creada correctamente.');

        $this->assertSame(
            ['2026-11-16T13:00:00.000000Z', '2026-11-23T13:00:00.000000Z', '2026-11-30T13:00:00.000000Z', '2026-12-07T13:00:00.000000Z'],
            $response->json('data.*.starts_at')
        );
        // La duración del primero (slot_minutes de la agenda, sin servicio) en todos.
        $this->assertSame(
            ['2026-11-16T13:30:00.000000Z', '2026-11-23T13:30:00.000000Z', '2026-11-30T13:30:00.000000Z', '2026-12-07T13:30:00.000000Z'],
            $response->json('data.*.ends_at')
        );

        $seriesId = $response->json('meta.series_id');
        $this->assertTrue(Str::isUuid($seriesId));
        $this->assertSame(array_fill(0, 4, $seriesId), $response->json('data.*.series_id'));
        $this->assertSame(array_fill(0, 4, 'pending_confirmation'), $response->json('data.*.status.value'));
        $this->assertSame(array_fill(0, 4, false), $response->json('data.*.is_overbooked'));

        // La base guarda la hora de Asunción.
        $this->assertSame(
            ['2026-11-16 10:00:00', '2026-11-23 10:00:00', '2026-11-30 10:00:00', '2026-12-07 10:00:00'],
            DB::table('appointments')->where('series_id', $seriesId)->orderBy('starts_at')->pluck('starts_at')->all()
        );

        // Cada turno, con su auditoría.
        $this->assertSame(4, DB::table('audit_logs')->where('auditable_type', 'appointment')->where('event', AuditEvent::CREATED->value)->count());
    }

    public function test_every_two_weeks_until_a_date_includes_the_last_one(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);

        $response = $this->bookSeries($schedule, ['every_weeks' => 2, 'until' => '2026-12-28'])->assertCreated();

        $this->assertSame(
            ['2026-11-16T13:00:00.000000Z', '2026-11-30T13:00:00.000000Z', '2026-12-14T13:00:00.000000Z', '2026-12-28T13:00:00.000000Z'],
            $response->json('data.*.starts_at')
        );

        // Un día antes de la cuarta fecha, tres turnos.
        $this->bookSeries($schedule, ['every_weeks' => 2, 'until' => '2026-12-27'], ['starts_at' => $this->at('11:00')])
            ->assertCreated()
            ->assertJsonCount(3, 'data');
    }

    public function test_the_series_keeps_the_clock_time_across_the_15th_of_october(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-09-20 07:00:00', 'America/Asuncion'));
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);

        $response = $this->bookSeries($schedule, ['count' => 3], ['starts_at' => '2027-10-11T10:00:00-03:00'])->assertCreated();

        $this->assertSame(
            ['2027-10-11T13:00:00.000000Z', '2027-10-18T13:00:00.000000Z', '2027-10-25T13:00:00.000000Z'],
            $response->json('data.*.starts_at')
        );
    }

    public function test_the_series_copies_the_patient_the_service_and_the_data_to_every_appointment(): void
    {
        $clinic = $this->createClinic();
        $service = $this->serviceIn($clinic, ['duration_minutes' => 45]);
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic), ['service_id' => $service->id]);
        $patient = $this->patientOf($this->clientIn($clinic));
        $this->actAsReception($clinic);

        $response = $this->bookSeries($schedule, ['count' => 2], [
            'contact_name' => null,
            'contact_phone' => null,
            'patient_id' => $patient->id,
            'reason' => 'Control semanal',
            'source' => 'phone',
        ])->assertCreated();

        $this->assertSame([$patient->id, $patient->id], $response->json('data.*.patient.id'));
        $this->assertSame([$patient->client_id, $patient->client_id], $response->json('data.*.client.id'));
        $this->assertSame([$service->id, $service->id], $response->json('data.*.service.id'));
        $this->assertSame(['Control semanal', 'Control semanal'], $response->json('data.*.reason'));
        $this->assertSame(['phone', 'phone'], $response->json('data.*.source.value'));
        $this->assertSame(['2026-11-16T13:45:00.000000Z', '2026-11-23T13:45:00.000000Z'], $response->json('data.*.ends_at'));
    }

    public function test_up_to_52_appointments(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);

        $response = $this->bookSeries($schedule, ['count' => 52])->assertCreated()->assertJsonCount(52, 'data');
        $this->assertSame('2027-11-08T13:00:00.000000Z', $response->json('data.51.starts_at'));

        // Hasta un año después del primero (2027-11-16) por fecha: el 2027-11-08 da 52.
        $this->bookSeries($schedule, ['until' => '2027-11-14'], ['starts_at' => $this->at('11:00')])
            ->assertCreated()
            ->assertJsonCount(52, 'data');
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string}>
     */
    public static function invalidRecurrences(): array
    {
        return [
            'count y until' => [['count' => 4, 'until' => '2026-12-31'], 'recurrence.count', 'Ingresá la cantidad de turnos o la fecha de fin de la serie, no las dos.'],
            'ni count ni until' => [['every_weeks' => 2], 'recurrence.count', 'Ingresá la cantidad de turnos o la fecha de fin de la serie.'],
            'más de 52' => [['count' => 53], 'recurrence.count', 'La serie no puede tener más de 52 turnos.'],
            'un solo turno' => [['count' => 1], 'recurrence.count', 'La serie tiene de 2 a 52 turnos.'],
            'until con más de 52' => [['until' => '2027-11-15'], 'recurrence.until', 'La serie no puede tener más de 52 turnos.'],
            'until a más de un año' => [['every_weeks' => 4, 'until' => '2027-11-17'], 'recurrence.until', 'La fecha de fin de la serie puede ser, como mucho, un año después del primer turno.'],
            'until antes del primero' => [['until' => '2026-11-15'], 'recurrence.until', 'La fecha de fin de la serie no puede ser anterior al primer turno.'],
            'until el mismo día' => [['until' => '2026-11-16'], 'recurrence.until', 'La serie tiene que tener al menos 2 turnos.'],
            'until con hora' => [['until' => '2026-12-31T10:00:00'], 'recurrence.until', 'Ingresá la fecha de fin de la serie (2026-12-31).'],
            'cada 0 semanas' => [['every_weeks' => 0, 'count' => 2], 'recurrence.every_weeks', 'La serie se repite cada 1 a 12 semanas.'],
            'cada 13 semanas' => [['every_weeks' => 13, 'count' => 2], 'recurrence.every_weeks', 'La serie se repite cada 1 a 12 semanas.'],
            'una clave de más' => [['count' => 2, 'weekdays' => [1, 3]], 'recurrence', 'La serie solo admite every_weeks, count y until.'],
        ];
    }

    /**
     * @param array<string, mixed> $recurrence
     */
    #[DataProvider('invalidRecurrences')]
    public function test_an_invalid_recurrence_is_rejected(array $recurrence, string $field, string $message): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);

        $response = $this->bookSeries($schedule, $recurrence)
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR');

        $this->assertSame($message, $response->json('errors')[$field][0] ?? null, json_encode($response->json('errors'), JSON_UNESCAPED_UNICODE));

        $this->assertSame(0, DB::table('appointments')->count());
    }

    public function test_a_null_recurrence_books_a_single_appointment(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);

        $this->book($schedule, ['recurrence' => null])
            ->assertCreated()
            ->assertJsonPath('data.series_id', null)
            ->assertJsonPath('meta', null);
    }

    public function test_the_first_date_follows_the_rules_of_a_single_appointment(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);

        // Un día que ya pasó.
        $this->bookSeries($schedule, ['count' => 3], ['starts_at' => $this->at('10:00', '2026-11-09')])
            ->assertUnprocessable()
            ->assertJsonPath('errors.starts_at.0', 'No podés dar un turno en un día que ya pasó.');

        // Hoy, a una hora que ya pasó, se puede (pregunta 16).
        $this->freezeToday('09:00:00');
        $this->bookSeries($schedule, ['count' => 2], ['starts_at' => $this->at('08:00', self::TODAY)])->assertCreated();

        // Una agenda inactiva.
        DB::table('schedules')->where('id', $schedule->id)->update(['is_active' => false]);
        $this->bookSeries($schedule, ['count' => 3])
            ->assertUnprocessable()
            ->assertJsonPath('errors.schedule_id.0', 'La agenda no existe o está inactiva.');

        $this->assertSame(2, DB::table('appointments')->count());
    }

    public function test_the_series_needs_the_store_permission(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actingIn($clinic, ['appointments.index']);

        $this->assertErrorCode($this->bookSeries($schedule, ['count' => 2]), 403, 'FORBIDDEN');
    }
}
