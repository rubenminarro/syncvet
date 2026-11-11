<?php

namespace Tests\Feature\Appointments\Series;

use App\Enums\AppointmentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * La serie entera o nada (FASE 3, entrega 3.3; P1): cada fecha pasa por las reglas del alta de un
 * turno (el horario semanal, los bloqueos y la superposición en la agenda y del veterinario). Si
 * una sola falla, no se crea ningún turno: 409 APPOINTMENT_SERIES_CONFLICT con errors.conflicts,
 * todas las fechas en conflicto con su motivo. overbook: true vale para todas las fechas (con el
 * permiso del sobreturno) y saltea solo la superposición.
 */
class AppointmentSeriesConflictTest extends TestCase
{
    use InteractsWithAppointments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeToday();
    }

    private function local(string $date, string $time): CarbonImmutable
    {
        return CarbonImmutable::parse("{$date} {$time}", 'America/Asuncion');
    }

    public function test_an_occupied_date_and_a_blocked_one_reject_the_whole_series_with_both_in_the_list(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);
        $this->appointmentIn($schedule, $this->local('2026-11-23', '10:15'));
        $this->blockFor($schedule, ['starts_at' => $this->local('2026-12-07', '09:00'), 'ends_at' => $this->local('2026-12-07', '12:00')]);

        $response = $this->bookSeries($schedule, ['count' => 5]);

        $this->assertErrorCode($response, 409, 'APPOINTMENT_SERIES_CONFLICT');
        $response
            ->assertJsonPath('message', 'Hay fechas de la serie que no se pueden dar: revisá la lista.')
            ->assertJsonPath('errors.conflicts', [
                [
                    'starts_at' => '2026-11-23T13:00:00.000000Z',
                    'reason' => 'overlap',
                    'message' => 'El horario ya está ocupado: elegí otro o dalo como sobreturno.',
                ],
                [
                    'starts_at' => '2026-12-07T13:00:00.000000Z',
                    'reason' => 'in_block',
                    'message' => 'La agenda está bloqueada en ese horario.',
                ],
            ]);

        // Ni las fechas libres: solo el turno que ya estaba.
        $this->assertSame(1, DB::table('appointments')->count());
        $this->assertSame(0, DB::table('audit_logs')->where('auditable_type', 'appointment')->count());
    }

    public function test_outside_the_hours_every_date_is_in_the_list(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->scheduleIn($this->defaultBranchIn($clinic), ['veterinarian_id' => null, 'service_id' => $this->serviceIn($clinic)->id]);
        // Los lunes, de 08:00 a 12:00: todas las fechas de la serie son lunes.
        $this->hoursFor($schedule, [[1, '08:00', '12:00']]);
        $this->actAsReception($clinic);

        $response = $this->bookSeries($schedule, ['count' => 3], ['starts_at' => $this->at('11:45')]);

        $this->assertErrorCode($response, 409, 'APPOINTMENT_SERIES_CONFLICT');
        $this->assertSame(['outside_hours', 'outside_hours', 'outside_hours'], $response->json('errors.conflicts.*.reason'));
        $this->assertSame(0, DB::table('appointments')->count());
    }

    public function test_another_schedule_of_the_same_veterinarian_counts(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $schedule = $this->openSchedule($branch);
        $other = $this->openSchedule($branch, ['veterinarian_id' => $schedule->veterinarian_id, 'name' => 'Otra agenda']);
        $this->actAsReception($clinic);
        $this->appointmentIn($other, $this->local('2026-11-30', '09:45'));

        $response = $this->bookSeries($schedule, ['count' => 3]);

        $this->assertErrorCode($response, 409, 'APPOINTMENT_SERIES_CONFLICT');
        $response->assertJsonPath('errors.conflicts.0.starts_at', '2026-11-30T13:00:00.000000Z');
        $response->assertJsonCount(1, 'errors.conflicts');
    }

    public function test_appointments_that_free_the_slot_do_not_collide(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);
        $this->appointmentIn($schedule, $this->local('2026-11-23', '10:00'), AppointmentStatus::CANCELLED);
        $this->appointmentIn($schedule, $this->local('2026-11-30', '10:00'), AppointmentStatus::NO_SHOW);
        // Uno que solo se toca (09:30 a 10:00) tampoco.
        $this->appointmentIn($schedule, $this->local('2026-12-07', '09:30'));

        $this->bookSeries($schedule, ['count' => 4])->assertCreated()->assertJsonCount(4, 'data');
    }

    public function test_overbook_applies_to_every_date_and_marks_only_the_ones_that_collide(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic, overbook: true);
        $this->appointmentIn($schedule, $this->local('2026-11-23', '10:00'));
        $this->appointmentIn($schedule, $this->local('2026-12-07', '10:00'));

        $response = $this->bookSeries($schedule, ['count' => 4], ['overbook' => true])->assertCreated();

        $this->assertSame([false, true, false, true], $response->json('data.*.is_overbooked'));
    }

    public function test_overbook_does_not_skip_the_blocks_nor_the_hours(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic, overbook: true);
        $this->appointmentIn($schedule, $this->local('2026-11-16', '10:00'));
        $this->blockFor($schedule, ['starts_at' => $this->local('2026-11-23', '10:00'), 'ends_at' => $this->local('2026-11-23', '10:30')]);

        $response = $this->bookSeries($schedule, ['count' => 3], ['overbook' => true]);

        $this->assertErrorCode($response, 409, 'APPOINTMENT_SERIES_CONFLICT');
        // La fecha ocupada no está en la lista: el sobreturno la admite.
        $response->assertJsonPath('errors.conflicts', [[
            'starts_at' => '2026-11-23T13:00:00.000000Z',
            'reason' => 'in_block',
            'message' => 'La agenda está bloqueada en ese horario.',
        ]]);

        $response = $this->bookSeries($schedule, ['count' => 2], ['overbook' => true, 'starts_at' => $this->at('19:45'), 'ends_at' => $this->at('20:15')]);
        $this->assertSame(['outside_hours', 'outside_hours'], $response->json('errors.conflicts.*.reason'));

        $this->assertSame(1, DB::table('appointments')->count());
    }

    public function test_overbook_in_a_series_needs_the_permission(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);

        $this->assertErrorCode($this->bookSeries($schedule, ['count' => 2], ['overbook' => true]), 403, 'FORBIDDEN');

        // El rol de la recepción lo tiene.
        Sanctum::actingAs($this->userWithRoles($clinic, ['recepcionista']));
        $this->bookSeries($schedule, ['count' => 2], ['overbook' => true])->assertCreated();
    }
}
