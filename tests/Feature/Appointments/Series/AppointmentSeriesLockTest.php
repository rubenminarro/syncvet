<?php

namespace Tests\Feature\Appointments\Series;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\InspectsQueries;
use Tests\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Concurrencia de la serie (FASE 3, entrega 3.3): la serie entera en una transacción, con el
 * veterinario y la agenda bloqueados una sola vez, en el orden del alta, antes de leer el
 * horario, los bloqueos y la superposición de cualquier fecha. Cancelar con alcance bloquea los
 * turnos de la serie (lockForUpdate, por id) antes de cambiarlos, sin tomar la agenda ni el
 * veterinario. El "for update" solo lo escribe MySQL.
 */
class AppointmentSeriesLockTest extends TestCase
{
    use InspectsQueries;
    use InteractsWithAppointments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeToday();
    }

    public function test_the_series_locks_the_veterinarian_and_the_schedule_once_before_reading_any_date(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);

        $queries = $this->queriesOf(fn () => $this->bookSeries($schedule, ['count' => 4])->assertCreated());

        $this->assertCount(1, array_filter($queries, $this->lockOf('veterinarians')), implode("\n", $queries));
        $this->assertCount(1, array_filter($queries, $this->lockOf('schedules')), implode("\n", $queries));
        $this->assertBefore($queries, $this->lockOf('veterinarians'), $this->lockOf('schedules'), 'La agenda se bloqueó antes que el veterinario.');
        $this->assertBefore($queries, $this->lockOf('schedules'), $this->readOf('schedule_hours'), 'Se leyó el horario antes de bloquear la agenda.');
        $this->assertBefore($queries, $this->lockOf('schedules'), $this->readOf('schedule_blocks'), 'Se leyeron los bloqueos antes de bloquear la agenda.');
        $this->assertBefore($queries, $this->lockOf('schedules'), $this->readOf('appointments'), 'Se leyó la superposición antes de bloquear la agenda.');

        // Las cuatro fechas se revisan antes de insertar la primera (todo o nada).
        $inserts = array_keys(array_filter($queries, fn (string $sql) => str_starts_with($sql, 'insert into') && str_contains($sql, 'appointments')));
        $overlapReads = array_keys(array_filter($queries, fn (string $sql) => str_starts_with($sql, 'select exists') && str_contains($sql, 'appointments')));
        $this->assertCount(4, $inserts);
        $this->assertCount(4, $overlapReads, implode("\n", $queries));
        $this->assertLessThan(min($inserts), max($overlapReads), 'Se insertó un turno antes de revisar todas las fechas.');
    }

    public function test_cancelling_the_series_locks_its_appointments_before_changing_them(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actingIn($clinic, self::TRANSITION_PERMISSIONS);
        $ids = $this->bookedSeries($schedule, ['count' => 3]);

        $queries = $this->queriesOf(fn () => $this->transition($ids[0], 'cancel', ['cancellation_reason' => 'Viaje', 'scope' => 'series'])->assertOk());

        $lock = fn (string $sql) => preg_match('/^select \* from [`"]appointments[`"] where [`"]series_id[`"] = \? .*order by [`"]id[`"] asc/', $sql) === 1
            && (DB::getDriverName() !== 'mysql' || str_ends_with($sql, 'for update'));
        $this->assertBefore($queries, $lock, fn (string $sql) => preg_match('/^update [`"]appointments[`"]/', $sql) === 1, 'Se cambió un turno antes de bloquear la serie.');
        $this->assertNull($this->firstIndex($queries, $this->lockOf('schedules')), 'La cancelación bloqueó la agenda.');
        $this->assertNull($this->firstIndex($queries, $this->lockOf('veterinarians')), 'La cancelación bloqueó el veterinario.');
    }
}
