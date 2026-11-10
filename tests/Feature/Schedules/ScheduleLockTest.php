<?php

namespace Tests\Feature\Schedules;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\InspectsQueries;
use Tests\Concerns\InteractsWithSchedules;
use Tests\TestCase;

/**
 * Concurrencia de agendas (FASE 3, entrega 3.1). Orden de bloqueo de la fase: clínica →
 * veterinario → servicio → agenda. El alta bloquea la clínica antes de leer los nombres y
 * toma el veterinario y el servicio con sharedLock; la edición los toma antes que la agenda;
 * la baja, el horario y los bloqueos bloquean la agenda antes de revisar los turnos o
 * escribir. Como ServiceLockTest, el orden de las consultas dentro de la transacción; el
 * "for update" y el "lock in share mode" solo los escribe MySQL.
 */
class ScheduleLockTest extends TestCase
{
    use InspectsQueries;
    use InteractsWithSchedules;

    public function test_creating_locks_the_clinic_then_the_veterinarian_and_the_service_before_reading_the_names(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $veterinarian = $this->veterinarianIn($branch);
        $service = $this->serviceIn($clinic);
        $this->actAsScheduleManager($clinic);

        $queries = $this->queriesOf(fn () => $this->postJson('/api/schedules', $this->schedulePayload($branch, [
            'veterinarian_id' => $veterinarian->id,
            'service_id' => $service->id,
        ]))->assertCreated());

        $this->assertBefore($queries, $this->lockOf('clinics'), $this->sharedLockOf('veterinarians'), 'El veterinario se leyó antes de bloquear la clínica.');
        $this->assertBefore($queries, $this->sharedLockOf('veterinarians'), $this->sharedLockOf('services'), 'El servicio se leyó antes que el veterinario.');
        $this->assertBefore($queries, $this->lockOf('clinics'), $this->namesRead(), 'Se leyeron los nombres antes de bloquear la clínica.');
    }

    public function test_updating_takes_the_veterinarian_and_the_service_before_the_schedule_and_checks_appointments_after(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $schedule = $this->scheduleIn($branch, ['veterinarian_id' => $this->veterinarianIn($branch)->id]);
        $newVeterinarian = $this->veterinarianIn($branch);
        $service = $this->serviceIn($clinic);
        $this->actAsScheduleManager($clinic);

        $queries = $this->queriesOf(fn () => $this->patchJson("/api/schedules/{$schedule->id}", [
            'veterinarian_id' => $newVeterinarian->id,
            'service_id' => $service->id,
        ])->assertOk());

        $this->assertBefore($queries, $this->sharedLockOf('veterinarians'), $this->lockOf('schedules'), 'La agenda se bloqueó antes que el veterinario.');
        $this->assertBefore($queries, $this->sharedLockOf('services'), $this->lockOf('schedules'), 'La agenda se bloqueó antes que el servicio.');
        $this->assertBefore($queries, $this->lockOf('schedules'), $this->readOf('appointments'), 'Se revisaron los turnos antes de bloquear la agenda.');
    }

    public function test_deleting_and_blocking_lock_the_schedule_first(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $schedule = $this->scheduleIn($branch, ['service_id' => $this->serviceIn($clinic)->id]);
        $this->actAsScheduleManager($clinic);

        $queries = $this->queriesOf(fn () => $this->postJson("/api/schedules/{$schedule->id}/blocks", [
            'starts_at' => now()->addDay()->setTime(8, 0)->format('Y-m-d\TH:i:s'),
            'ends_at' => now()->addDay()->setTime(9, 0)->format('Y-m-d\TH:i:s'),
        ])->assertCreated());
        $this->assertBefore($queries, $this->lockOf('schedules'), fn (string $sql) => str_starts_with($sql, 'insert into'), 'El bloqueo se creó antes de bloquear la agenda.');

        $queries = $this->queriesOf(fn () => $this->patchJson("/api/schedules/{$schedule->id}", ['hours' => [['weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '09:00']]])->assertOk());
        $this->assertBefore($queries, $this->lockOf('schedules'), fn (string $sql) => str_contains($sql, 'schedule_hours'), 'Se tocó el horario antes de bloquear la agenda.');

        $queries = $this->queriesOf(fn () => $this->deleteJson("/api/schedules/{$schedule->id}")->assertOk());
        $this->assertBefore($queries, $this->lockOf('schedules'), $this->readOf('appointments'), 'Se revisaron los turnos antes de bloquear la agenda.');
        $this->assertSame(0, DB::table('schedules')->count());
    }

    /**
     * La lectura de una fila por su id; en MySQL, con "lock in share mode".
     */
    private function sharedLockOf(string $table): callable
    {
        return fn (string $sql) => preg_match('/^select \* from [`"]' . $table . '[`"] where .*[`"]' . $table . '[`"]\.[`"]id[`"] = \?/', $sql) === 1
            && (DB::getDriverName() !== 'mysql' || str_ends_with($sql, 'lock in share mode'));
    }

    private function namesRead(): callable
    {
        return fn (string $sql) => preg_match('/^select [`"]name[`"] from [`"]schedules[`"]/', $sql) === 1;
    }
}
