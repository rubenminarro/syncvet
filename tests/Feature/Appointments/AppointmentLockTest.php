<?php

namespace Tests\Feature\Appointments;

use App\Services\AppointmentService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\Concerns\InspectsQueries;
use Tests\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Concurrencia de turnos (FASE 3, entrega 3.2a). Orden de bloqueo de la fase: veterinario →
 * servicio → agenda → paciente → turno, y con todo eso bloqueado se leen los tramos, los
 * bloqueos y los turnos que se superponen. Si la agenda cambia de veterinario entre la lectura
 * y el bloqueo, o MySQL informa un deadlock, se repite; después de los intentos, 409 CONFLICT
 * (nunca un 500). Como ScheduleLockTest, el orden de las consultas dentro de la transacción; el
 * "for update" y el "lock in share mode" solo los escribe MySQL.
 */
class AppointmentLockTest extends TestCase
{
    use InspectsQueries;
    use InteractsWithAppointments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeToday();
    }

    public function test_creating_locks_veterinarian_service_schedule_and_patient_before_reading_the_slot(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic), ['service_id' => null]);
        $service = $this->serviceIn($clinic);
        $patient = $this->patientOf($this->clientIn($clinic));
        $this->actAsReception($clinic);

        $queries = $this->queriesOf(fn () => $this->book($schedule, [
            'service_id' => $service->id, 'patient_id' => $patient->id, 'contact_name' => null, 'contact_phone' => null,
        ])->assertCreated());

        $this->assertBefore($queries, $this->lockOf('veterinarians'), $this->sharedLockOf('services'), 'El servicio se bloqueó antes que el veterinario.');
        $this->assertBefore($queries, $this->sharedLockOf('services'), $this->lockOf('schedules'), 'La agenda se bloqueó antes que el servicio.');
        $this->assertBefore($queries, $this->lockOf('schedules'), $this->sharedLockOf('patients'), 'El paciente se bloqueó antes que la agenda.');
        $this->assertBefore($queries, $this->sharedLockOf('patients'), $this->sharedLockOf('clients'), 'El cliente se bloqueó antes que el paciente.');
        $this->assertBefore($queries, $this->lockOf('schedules'), $this->readOf('schedule_hours'), 'Se leyó el horario antes de bloquear la agenda.');
        $this->assertBefore($queries, $this->lockOf('schedules'), $this->readOf('schedule_blocks'), 'Se leyeron los bloqueos antes de bloquear la agenda.');
        $this->assertBefore($queries, $this->lockOf('schedules'), $this->readOf('appointments'), 'Se leyó la superposición antes de bloquear la agenda.');
        $this->assertNoneAfter($queries, $this->lockOf('schedules'), $this->lockOf('veterinarians'), 'Se bloqueó un veterinario después de la agenda.');
    }

    public function test_moving_locks_the_veterinarian_then_both_schedules_then_the_appointment(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $schedule = $this->openSchedule($branch);
        $target = $this->openSchedule($branch);
        $this->actAsReception($clinic);
        $id = $this->booked($schedule);

        $queries = $this->queriesOf(fn () => $this->patchJson("/api/appointments/{$id}", ['schedule_id' => $target->id])->assertOk());

        $locks = array_values(array_filter($queries, $this->lockOf('schedules')));
        $this->assertCount(2, $locks, "Se esperaban las dos agendas bloqueadas:\n" . implode("\n", $queries));
        $this->assertBefore($queries, $this->lockOf('veterinarians'), $this->lockOf('schedules'), 'La agenda se bloqueó antes que el veterinario.');
        $this->assertBefore($queries, $this->lockOf('schedules'), $this->lockOf('appointments'), 'El turno se bloqueó antes que la agenda.');
        $this->assertBefore($queries, $this->lockOf('appointments'), $this->readOf('schedule_hours'), 'Se leyó el horario antes de bloquear el turno.');
        $this->assertNoneAfter($queries, $this->lockOf('schedules'), $this->lockOf('veterinarians'), 'Se bloqueó un veterinario después de la agenda.');
    }

    /**
     * Pregunta 21: las bajas del cliente y del paciente revisan el uso con su fila bloqueada.
     */
    public function test_deleting_a_client_or_a_patient_locks_it_before_checking_the_appointments(): void
    {
        $clinic = $this->createClinic();
        $client = $this->clientIn($clinic);
        $patient = $this->patientOf($this->clientIn($clinic));
        $this->actingIn($clinic, ['clients.destroy', 'patients.destroy']);

        $queries = $this->queriesOf(fn () => $this->deleteJson("/api/clients/{$client->id}")->assertOk());
        $this->assertBefore($queries, $this->lockOf('clients'), $this->readOf('appointments'), 'Se revisaron los turnos antes de bloquear el cliente.');

        $queries = $this->queriesOf(fn () => $this->deleteJson("/api/patients/{$patient->id}")->assertOk());
        $this->assertBefore($queries, $this->lockOf('patients'), $this->readOf('appointments'), 'Se revisaron los turnos antes de bloquear el paciente.');
    }

    public function test_the_creation_is_retried_if_the_veterinarian_of_the_schedule_changes_while_locking(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $schedule = $this->openSchedule($branch);
        $changed = $this->veterinarianIn($branch);
        $this->actAsReception($clinic);

        $reads = $this->afterEachScheduleRead(function (int $read) use ($schedule, $changed) {
            if ($read === 1) {
                DB::table('schedules')->where('id', $schedule->id)->update(['veterinarian_id' => $changed->id]);
            }
        });

        $this->book($schedule)->assertCreated()->assertJsonPath('data.veterinarian.id', $changed->id);
        $this->assertSame(2, $reads->count);
    }

    public function test_after_the_attempts_the_creation_answers_409(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $schedule = $this->openSchedule($branch);
        $first = $schedule->veterinarian_id;
        $second = $this->veterinarianIn($branch)->id;
        $this->actAsReception($clinic);

        $reads = $this->afterEachScheduleRead(function (int $read) use ($schedule, $first, $second) {
            DB::table('schedules')->where('id', $schedule->id)->update(['veterinarian_id' => $read % 2 === 1 ? $second : $first]);
        });

        $response = $this->book($schedule);

        $this->assertErrorCode($response, 409, 'CONFLICT');
        $response->assertJsonPath('message', 'La agenda cambió mientras se guardaba el turno: volvé a intentarlo.');
        $this->assertSame(AppointmentService::ATTEMPTS, $reads->count);
        $this->assertSame(0, DB::table('appointments')->count());
    }

    /**
     * Un deadlock de MySQL (1213) se repite; si sigue, 409 CONFLICT y nunca un 500. Se simula
     * al bloquear el veterinario.
     */
    public function test_a_deadlock_is_retried_and_never_answers_500(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);

        $deadlocks = $this->deadlockOnVeterinarianLock(times: 1);
        $this->book($schedule)->assertCreated();
        $this->assertSame(1, $deadlocks->count);

        $deadlocks = $this->deadlockOnVeterinarianLock(times: PHP_INT_MAX);
        $this->assertErrorCode($this->book($schedule, ['starts_at' => $this->at('11:00')]), 409, 'CONFLICT');
        $this->assertSame(AppointmentService::ATTEMPTS, $deadlocks->count);
        $this->assertSame(1, DB::table('appointments')->count());
    }

    public function test_a_deadlock_while_moving_is_retried(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);
        $id = $this->booked($schedule);

        $deadlocks = $this->deadlockOnVeterinarianLock(times: PHP_INT_MAX);
        $this->assertErrorCode($this->patchJson("/api/appointments/{$id}", ['starts_at' => $this->at('11:00')]), 409, 'CONFLICT');
        $this->assertSame(AppointmentService::ATTEMPTS, $deadlocks->count);
        $this->assertSame('2026-11-16 10:00:00', $this->appointmentRow($id)->starts_at);
    }

    /**
     * Después de cada lectura sin bloqueo de la agenda (su veterinario y su servicio), corre
     * $change con el número de lectura (desde 1): simula otra edición antes del bloqueo.
     *
     * @param callable(int): void $change
     */
    private function afterEachScheduleRead(callable $change): object
    {
        $reads = (object) ['count' => 0];
        $changing = false;

        DB::listen(function ($query) use ($change, $reads, &$changing) {
            if ($changing || preg_match('/^select [`"]id[`"], [`"]veterinarian_id[`"], [`"]service_id[`"] from [`"]schedules[`"]/i', $query->sql) !== 1) {
                return;
            }

            $reads->count++;
            $changing = true;
            $change($reads->count);
            $changing = false;
        });

        return $reads;
    }

    /**
     * Las primeras $times veces que se bloquea un veterinario, la consulta falla como un
     * deadlock de MySQL.
     */
    private function deadlockOnVeterinarianLock(int $times): object
    {
        $deadlocks = (object) ['count' => 0, 'active' => true];

        DB::listen(function ($query) use ($times, $deadlocks) {
            if (! $deadlocks->active || $deadlocks->count >= $times || ! ($this->lockOf('veterinarians'))(strtolower($query->sql))) {
                return;
            }

            $deadlocks->count++;
            $error = new PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction');

            throw new QueryException($query->connectionName, $query->sql, $query->bindings, $error);
        });

        // Un listener anterior de otro escenario queda inactivo.
        $this->beforeApplicationDestroyed(fn () => $deadlocks->active = false);

        return $deadlocks;
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
}
