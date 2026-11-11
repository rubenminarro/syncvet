<?php

namespace Tests\Feature\Appointments;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * El horario semanal y los bloqueos (FASE 3, entrega 3.2a; P6): el turno entero dentro de un
 * tramo del día (en hora de Asunción) y sin superponerse con un bloqueo; si no, 422 siempre.
 */
class AppointmentScheduleRulesTest extends TestCase
{
    use InteractsWithAppointments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeToday();
    }

    /**
     * Agenda con dos tramos los lunes (08:00–12:00 y 14:00–18:00) y nada los martes.
     */
    private function mondaySchedule(): \App\Models\Schedule
    {
        $clinic = $this->createClinic();
        $schedule = $this->scheduleIn($this->defaultBranchIn($clinic), ['veterinarian_id' => null, 'service_id' => $this->serviceIn($clinic)->id]);
        $this->hoursFor($schedule, [[1, '08:00', '12:00'], [1, '14:00', '18:00']]);
        $this->actAsReception($clinic);

        return $schedule;
    }

    public static function outsideHours(): array
    {
        return [
            'antes del tramo' => ['07:30', '08:00', self::MONDAY],
            'empieza dentro y termina fuera' => ['11:30', '12:30', self::MONDAY],
            'entre los dos tramos' => ['12:00', '14:00', self::MONDAY],
            'abarca los dos tramos' => ['11:00', '15:00', self::MONDAY],
            'después del tramo' => ['18:00', '18:30', self::MONDAY],
            'un día sin tramos (martes)' => ['10:00', '10:30', '2026-11-17'],
        ];
    }

    #[DataProvider('outsideHours')]
    public function test_outside_the_weekly_hours_is_rejected(string $from, string $to, string $date): void
    {
        $schedule = $this->mondaySchedule();

        $response = $this->book($schedule, ['starts_at' => $this->at($from, $date), 'ends_at' => $this->at($to, $date)]);

        $this->assertErrorCode($response, 422, 'APPOINTMENT_OUTSIDE_HOURS');
        $response->assertJsonPath('message', 'El turno está fuera del horario de la agenda.');
        $this->assertSame(0, DB::table('appointments')->count());
    }

    public function test_the_exact_edges_of_a_range_are_inside(): void
    {
        $schedule = $this->mondaySchedule();

        $this->book($schedule, ['starts_at' => $this->at('08:00'), 'ends_at' => $this->at('08:30')])->assertCreated();
        $this->book($schedule, ['starts_at' => $this->at('11:30'), 'ends_at' => $this->at('12:00')])->assertCreated();
        $this->book($schedule, ['starts_at' => $this->at('14:00'), 'ends_at' => $this->at('18:00')])->assertCreated();
    }

    /**
     * Decisión del usuario (revisión de la 3.2a): dos tramos del mismo día que se tocan
     * (08:00–12:00 y 12:00–16:00, y 16:00–18:00 detrás) son un horario continuo; un turno que
     * cruza el punto donde se tocan entra. Con un hueco real (18:30–20:00) sigue fuera.
     */
    public function test_touching_ranges_are_one_continuous_range_and_a_gap_is_not(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->scheduleIn($this->defaultBranchIn($clinic), ['veterinarian_id' => null, 'service_id' => $this->serviceIn($clinic)->id]);
        // Cargados fuera de orden: el Service los ordena.
        $this->hoursFor($schedule, [[1, '12:00', '16:00'], [1, '08:00', '12:00'], [1, '16:00', '18:00'], [1, '18:30', '20:00']]);
        $this->actAsReception($clinic);

        $this->book($schedule, ['starts_at' => $this->at('11:30'), 'ends_at' => $this->at('12:30')])->assertCreated();
        $this->book($schedule, ['starts_at' => $this->at('15:30'), 'ends_at' => $this->at('16:30')])->assertCreated();
        // Cruza los dos puntos donde se tocan (12:00 y 16:00), sin chocar con los anteriores: el otro lunes.
        $this->book($schedule, ['starts_at' => $this->at('08:00', '2026-11-23'), 'ends_at' => $this->at('18:00', '2026-11-23')])->assertCreated();

        foreach ([['17:30', '18:45'], ['18:00', '18:30'], ['17:00', '19:00']] as [$from, $to]) {
            $this->assertErrorCode($this->book($schedule, ['starts_at' => $this->at($from), 'ends_at' => $this->at($to)]), 422, 'APPOINTMENT_OUTSIDE_HOURS');
        }

        $this->book($schedule, ['starts_at' => $this->at('18:30'), 'ends_at' => $this->at('20:00')])->assertCreated();
    }

    /**
     * El día de la semana sale de la hora de Asunción: el lunes 16 a las 23:30 UTC ya es el
     * lunes 16 a las 20:30 en Asunción, fuera del horario; el lunes 16 a las 02:00 UTC es el
     * domingo 15 a las 23:00, también fuera.
     */
    public function test_the_weekday_is_the_one_of_asuncion(): void
    {
        $schedule = $this->mondaySchedule();

        $this->assertErrorCode($this->book($schedule, ['starts_at' => '2026-11-16T02:00:00Z', 'ends_at' => '2026-11-16T02:30:00Z']), 422, 'APPOINTMENT_OUTSIDE_HOURS');
        $this->book($schedule, ['starts_at' => '2026-11-16T11:00:00Z', 'ends_at' => '2026-11-16T11:30:00Z'])->assertCreated();
    }

    public function test_a_block_that_overlaps_is_rejected_and_one_that_touches_is_not(): void
    {
        $schedule = $this->mondaySchedule();
        $this->blockFor($schedule, [
            'starts_at' => CarbonImmutable::parse(self::MONDAY . ' 10:00', 'America/Asuncion'),
            'ends_at' => CarbonImmutable::parse(self::MONDAY . ' 11:00', 'America/Asuncion'),
        ]);

        $inside = $this->book($schedule, ['starts_at' => $this->at('10:30'), 'ends_at' => $this->at('10:45')]);
        $this->assertErrorCode($inside, 422, 'APPOINTMENT_IN_BLOCK');
        $inside->assertJsonPath('message', 'La agenda está bloqueada en ese horario.');

        $this->assertErrorCode($this->book($schedule, ['starts_at' => $this->at('09:45'), 'ends_at' => $this->at('10:15')]), 422, 'APPOINTMENT_IN_BLOCK');
        $this->assertErrorCode($this->book($schedule, ['starts_at' => $this->at('09:00'), 'ends_at' => $this->at('11:30')]), 422, 'APPOINTMENT_IN_BLOCK');

        $this->book($schedule, ['starts_at' => $this->at('09:30'), 'ends_at' => $this->at('10:00')])->assertCreated();
        $this->book($schedule, ['starts_at' => $this->at('11:00'), 'ends_at' => $this->at('11:30')])->assertCreated();
    }

    public function test_a_block_of_another_schedule_does_not_matter(): void
    {
        $schedule = $this->mondaySchedule();
        $other = $this->scheduleIn($this->branchOf($schedule), ['service_id' => $schedule->service_id]);
        $this->blockFor($other, [
            'starts_at' => CarbonImmutable::parse(self::MONDAY . ' 08:00', 'America/Asuncion'),
            'ends_at' => CarbonImmutable::parse(self::MONDAY . ' 18:00', 'America/Asuncion'),
        ]);

        $this->book($schedule)->assertCreated();
    }
}
