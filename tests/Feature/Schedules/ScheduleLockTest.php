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

    /**
     * Hallazgo 1 de la revisión: un PATCH que cambia solo la sucursal toma el veterinario de
     * la agenda (para ver que trabaja en la nueva) antes de bloquearla, nunca después.
     */
    public function test_changing_only_the_branch_locks_the_veterinarian_before_the_schedule(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $other = $this->branchIn($clinic);
        $schedule = $this->scheduleIn($branch, ['veterinarian_id' => $this->veterinarianIn($branch, $other)->id]);
        $this->actAsScheduleManager($clinic);

        $queries = $this->queriesOf(fn () => $this->patchJson("/api/schedules/{$schedule->id}", ['branch_id' => $other->id])
            ->assertOk()
            ->assertJsonPath('data.branch.id', $other->id));

        $this->assertBefore($queries, $this->sharedLockOf('veterinarians'), $this->lockOf('schedules'), 'La agenda se bloqueó antes que el veterinario.');
        $this->assertNoneAfter($queries, $this->lockOf('schedules'), $this->sharedLockOf('veterinarians'), 'Se leyó el veterinario después de bloquear la agenda.');
    }

    public function test_changing_the_veterinarian_locks_both_veterinarians_before_the_schedule(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $schedule = $this->scheduleIn($branch, ['veterinarian_id' => $this->veterinarianIn($branch)->id]);
        $newVeterinarian = $this->veterinarianIn($branch);
        $this->actAsScheduleManager($clinic);

        $queries = $this->queriesOf(fn () => $this->patchJson("/api/schedules/{$schedule->id}", ['veterinarian_id' => $newVeterinarian->id])->assertOk());

        $locks = array_values(array_filter($queries, $this->sharedLockOf('veterinarians')));
        $this->assertCount(2, $locks, "Se esperaban los dos veterinarios bloqueados:\n" . implode("\n", $queries));
        $this->assertNoneAfter($queries, $this->lockOf('schedules'), $this->sharedLockOf('veterinarians'), 'Se leyó un veterinario después de bloquear la agenda.');
    }

    /**
     * Si el veterinario de la agenda cambia entre la lectura sin bloqueo y el bloqueo, la
     * edición se repite con el veterinario nuevo.
     */
    public function test_the_update_is_retried_if_the_veterinarian_changes_while_locking(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $other = $this->branchIn($clinic);
        $schedule = $this->scheduleIn($branch, ['veterinarian_id' => $this->veterinarianIn($branch, $other)->id]);
        $changed = $this->veterinarianIn($branch, $other);
        $this->actAsScheduleManager($clinic);

        $reads = $this->changeVeterinarianAfterEachRead($schedule->id, fn (int $read) => $read === 1 ? $changed->id : null);

        $this->patchJson("/api/schedules/{$schedule->id}", ['branch_id' => $other->id])
            ->assertOk()
            ->assertJsonPath('data.branch.id', $other->id)
            ->assertJsonPath('data.veterinarian.id', $changed->id);

        $this->assertSame(2, $reads->count);
    }

    public function test_after_the_attempts_the_update_answers_409(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $other = $this->branchIn($clinic);
        $first = $this->veterinarianIn($branch, $other);
        $second = $this->veterinarianIn($branch, $other);
        $schedule = $this->scheduleIn($branch, ['veterinarian_id' => $first->id]);
        $this->actAsScheduleManager($clinic);

        // Cambia en cada intento.
        $reads = $this->changeVeterinarianAfterEachRead($schedule->id, fn (int $read) => $read % 2 === 1 ? $second->id : $first->id);

        $response = $this->patchJson("/api/schedules/{$schedule->id}", ['branch_id' => $other->id]);

        $this->assertErrorCode($response, 409, 'CONFLICT');
        $response->assertJsonPath('message', 'La agenda cambió mientras se editaba: volvé a intentarlo.');
        $this->assertSame(\App\Services\ScheduleService::UPDATE_ATTEMPTS, $reads->count);
        $this->assertSame($branch->id, (int) $this->scheduleRow($schedule->id)->branch_id);
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
     * Simula otra edición entre la lectura sin bloqueo del veterinario de la agenda y su
     * bloqueo: después de cada lectura, pone en la agenda el veterinario que devuelve
     * $veterinarianFor (o nada, si devuelve null).
     *
     * @param callable(int): ?int $veterinarianFor recibe el número de lectura, desde 1
     */
    private function changeVeterinarianAfterEachRead(int $scheduleId, callable $veterinarianFor): object
    {
        $reads = (object) ['count' => 0];
        $changing = false;

        DB::listen(function ($query) use ($scheduleId, $veterinarianFor, $reads, &$changing) {
            if ($changing || preg_match('/^select [`"]id[`"], [`"]veterinarian_id[`"] from [`"]schedules[`"]/i', $query->sql) !== 1) {
                return;
            }

            $reads->count++;
            $veterinarianId = $veterinarianFor($reads->count);

            if ($veterinarianId !== null) {
                $changing = true;
                DB::table('schedules')->where('id', $scheduleId)->update(['veterinarian_id' => $veterinarianId]);
                $changing = false;
            }
        });

        return $reads;
    }

    /**
     * Ninguna consulta $then después de la primera $first.
     *
     * @param list<string> $queries
     */
    private function assertNoneAfter(array $queries, callable $first, callable $then, string $message): void
    {
        $index = $this->firstIndex($queries, $first);
        $this->assertNotNull($index, "No se encontró la primera consulta:\n" . implode("\n", $queries));

        foreach (array_slice($queries, $index + 1) as $sql) {
            $this->assertFalse($then($sql), $message . "\n" . implode("\n", $queries));
        }
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
