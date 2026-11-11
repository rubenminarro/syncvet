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

    private const DEADLOCK = 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction';

    private const LOCK_WAIT_TIMEOUT = 'SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded; try restarting transaction';

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

        // Solo los tramos del día del turno (ScheduleOccupancy lee los días de la ventana).
        $hours = $queries[$this->firstIndex($queries, $this->readOf('schedule_hours'))];
        $this->assertMatchesRegularExpression('/[`"]weekday[`"] in \(/', $hours);
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
     * Revisión de la 3.2a (hallazgo 4): al mover, si el veterinario de la agenda de destino
     * cambia entre la lectura y el bloqueo, el que se bloqueó ya no es el suyo: se repite.
     */
    public function test_moving_is_retried_if_the_veterinarian_of_the_target_schedule_changes_while_locking(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $schedule = $this->openSchedule($branch);
        $target = $this->openSchedule($branch);
        $changed = $this->veterinarianIn($branch);
        $this->actAsReception($clinic);
        $id = $this->booked($schedule);

        $reads = $this->afterEachScheduleRead(function (int $read) use ($target, $changed) {
            if ($read === 1) {
                DB::table('schedules')->where('id', $target->id)->update(['veterinarian_id' => $changed->id]);
            }
        });

        $this->patchJson("/api/appointments/{$id}", ['schedule_id' => $target->id])
            ->assertOk()
            ->assertJsonPath('data.schedule.id', $target->id)
            ->assertJsonPath('data.veterinarian.id', $changed->id);
        $this->assertSame(2, $reads->count);
    }

    /**
     * Revisión de la 3.2a (hallazgo 4): si el turno cambia de agenda entre la lectura y el
     * bloqueo, las agendas bloqueadas ya no son las suyas: se repite con la agenda nueva, y
     * después de los intentos, 409 CONFLICT sin cambios.
     */
    public function test_moving_is_retried_if_the_appointment_changes_its_schedule_while_locking(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $schedule = $this->openSchedule($branch);
        $other = $this->openSchedule($branch);
        $this->actAsReception($clinic);
        $id = $this->booked($schedule);

        $reads = $this->afterEachAppointmentRead(function (int $read) use ($id, $other) {
            if ($read === 1) {
                DB::table('appointments')->where('id', $id)->update(['schedule_id' => $other->id, 'veterinarian_id' => $other->veterinarian_id]);
            }
        });

        $this->patchJson("/api/appointments/{$id}", ['starts_at' => $this->at('11:00')])
            ->assertOk()
            ->assertJsonPath('data.schedule.id', $other->id)
            ->assertJsonPath('data.starts_at', '2026-11-16T14:00:00.000000Z');
        $this->assertSame(2, $reads->count);
    }

    public function test_after_the_attempts_moving_answers_409(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $schedule = $this->openSchedule($branch);
        $other = $this->openSchedule($branch);
        $this->actAsReception($clinic);
        $id = $this->booked($schedule);

        $reads = $this->afterEachAppointmentRead(function (int $read) use ($id, $schedule, $other) {
            $to = $read % 2 === 1 ? $other : $schedule;
            DB::table('appointments')->where('id', $id)->update(['schedule_id' => $to->id, 'veterinarian_id' => $to->veterinarian_id]);
        });

        $this->assertErrorCode($this->patchJson("/api/appointments/{$id}", ['starts_at' => $this->at('11:00')]), 409, 'CONFLICT');
        $this->assertSame(AppointmentService::ATTEMPTS, $reads->count);
        $this->assertSame('2026-11-16 10:00:00', $this->appointmentRow($id)->starts_at);
    }

    /**
     * Revisión de la 3.2a (hallazgo 7): una espera vencida de MySQL (1205) no se repite: 409
     * CONFLICT enseguida, en el alta y al mover. El deadlock (1213) sí se repite (arriba).
     */
    public function test_a_lock_wait_timeout_is_not_retried_and_answers_409(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);
        $id = $this->booked($schedule);

        $timeouts = $this->failOnVeterinarianLock(PHP_INT_MAX, self::LOCK_WAIT_TIMEOUT);
        $response = $this->book($schedule, ['starts_at' => $this->at('11:00')]);
        $this->assertErrorCode($response, 409, 'CONFLICT');
        $response->assertJsonPath('message', 'La agenda cambió mientras se guardaba el turno: volvé a intentarlo.');
        $this->assertSame(1, $timeouts->count);

        $this->assertErrorCode($this->patchJson("/api/appointments/{$id}", ['starts_at' => $this->at('12:00')]), 409, 'CONFLICT');
        $this->assertSame(2, $timeouts->count);
        $this->assertSame(1, DB::table('appointments')->count());
        $this->assertSame('2026-11-16 10:00:00', $this->appointmentRow($id)->starts_at);
    }

    /**
     * Revisión de la 3.2a (hallazgo 8): el cambio de dueño bloquea el paciente y después los dos
     * clientes por id, en el sentido de los turnos (paciente → cliente).
     */
    public function test_changing_the_owner_locks_the_patient_then_both_clients_by_id(): void
    {
        $clinic = $this->createClinic();
        $newOwner = $this->clientIn($clinic);
        $patient = $this->patientOf($this->clientIn($clinic));
        $this->actingIn($clinic, ['patients.change-owner']);

        $clientLocks = [];
        $base = DB::transactionLevel();
        DB::listen(function ($query) use (&$clientLocks, $base) {
            if ($query->connection->transactionLevel() > $base && ($this->lockOf('clients'))(strtolower($query->sql))) {
                $clientLocks[] = (int) $query->bindings[0];
            }
        });

        $queries = $this->queriesOf(fn () => $this->postJson("/api/patients/{$patient->id}/change-owner", ['client_id' => $newOwner->id])->assertOk());

        $this->assertBefore($queries, $this->lockOf('patients'), $this->lockOf('clients'), 'Se bloqueó un cliente antes que el paciente.');
        $this->assertNoneAfter($queries, $this->lockOf('clients'), $this->lockOf('patients'), 'Se bloqueó el paciente después de un cliente.');
        $expected = [$newOwner->id, $patient->client_id];
        sort($expected);
        $this->assertSame($expected, $clientLocks);
    }

    /**
     * Si el dueño nuevo se elimina entre la validación y el bloqueo, 422 en client_id y el
     * paciente no cambia.
     */
    public function test_changing_to_an_owner_deleted_while_locking_is_rejected(): void
    {
        $clinic = $this->createClinic();
        $newOwner = $this->clientIn($clinic);
        $patient = $this->patientOf($this->clientIn($clinic));
        $this->actingIn($clinic, ['patients.change-owner']);

        $deleting = false;
        DB::listen(function ($query) use ($newOwner, &$deleting) {
            if (! $deleting && ($this->lockOf('patients'))(strtolower($query->sql))) {
                $deleting = true;
                DB::table('clients')->where('id', $newOwner->id)->update(['deleted_at' => now()]);
            }
        });

        $this->postJson("/api/patients/{$patient->id}/change-owner", ['client_id' => $newOwner->id])
            ->assertUnprocessable()->assertJsonPath('errors.client_id.0', 'El cliente seleccionado no existe.');
        $this->assertNotSame($newOwner->id, (int) DB::table('patients')->where('id', $patient->id)->value('client_id'));
    }

    /**
     * Después de cada lectura sin bloqueo del turno (al editar), corre $change con el número de
     * lectura (desde 1): simula otra edición antes del bloqueo.
     *
     * @param callable(int): void $change
     */
    private function afterEachAppointmentRead(callable $change): object
    {
        $reads = (object) ['count' => 0];
        $changing = false;

        DB::listen(function ($query) use ($change, $reads, &$changing) {
            if ($changing || preg_match('/^select [`"]id[`"], [`"]schedule_id[`"], [`"]patient_id[`"], [`"]client_id[`"], [`"]status[`"] from [`"]appointments[`"]/i', $query->sql) !== 1) {
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
        return $this->failOnVeterinarianLock($times, self::DEADLOCK);
    }

    /**
     * Las primeras $times veces que se bloquea un veterinario, la consulta falla con el error
     * de MySQL $message (un deadlock o una espera vencida).
     */
    private function failOnVeterinarianLock(int $times, string $message): object
    {
        $deadlocks = (object) ['count' => 0, 'active' => true];

        DB::listen(function ($query) use ($times, $message, $deadlocks) {
            if (! $deadlocks->active || $deadlocks->count >= $times || ! ($this->lockOf('veterinarians'))(strtolower($query->sql))) {
                return;
            }

            $deadlocks->count++;
            $error = new PDOException($message);

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
