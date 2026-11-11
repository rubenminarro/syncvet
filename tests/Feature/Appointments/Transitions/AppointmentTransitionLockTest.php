<?php

namespace Tests\Feature\Appointments\Transitions;

use App\Enums\AppointmentStatus;
use App\Services\AppointmentService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InspectsQueries;
use Tests\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Concurrencia de las transiciones (FASE 3, entrega 3.2b). Cada una bloquea el turno
 * (lockForUpdate) antes de leer su estado y no bloquea la agenda ni el veterinario: solo libera
 * horario. Deshacer el ausente lo vuelve a ocupar y bloquea como un alta: veterinario → agenda →
 * turno, y repite si la agenda cambió de veterinario en el medio. Un deadlock se repite y, si
 * sigue, 409 CONFLICT; una espera vencida, 409 enseguida: nunca un 500. El "for update" solo lo
 * escribe MySQL (en SQLite se ve el orden de las lecturas).
 */
class AppointmentTransitionLockTest extends TestCase
{
    use InspectsQueries;
    use InteractsWithAppointments;

    private const DEADLOCK = 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction';

    private const LOCK_WAIT_TIMEOUT = 'SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded; try restarting transaction';

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeToday('11:00:00');
    }

    #[DataProvider('simpleTransitions')]
    public function test_a_transition_locks_the_appointment_before_reading_its_status(string $action, AppointmentStatus $from): void
    {
        $schedule = $this->openSchedule($this->defaultBranchIn($this->createClinic()));
        $id = $this->appointmentIn($schedule, $this->today('10:00'), $from);
        $this->actingIn($schedule->clinic, [...self::TRANSITION_PERMISSIONS, 'appointments.attend']);

        $queries = $this->queriesOf(fn () => $this->transition($id, $action, ['cancellation_reason' => 'Motivo'])->assertOk());

        $this->assertTrue(($this->lockOf('appointments'))($queries[0] ?? ''), "La primera consulta no es el bloqueo del turno:\n" . implode("\n", $queries));
        $this->assertNull($this->firstIndex($queries, $this->lockOf('schedules')), 'Una transición no bloquea la agenda.');
        $this->assertNull($this->firstIndex($queries, $this->lockOf('veterinarians')), 'Una transición no bloquea el veterinario.');
    }

    public static function simpleTransitions(): array
    {
        return [
            'confirmar' => ['confirm', AppointmentStatus::PENDING_CONFIRMATION],
            'fichar' => ['check-in', AppointmentStatus::CONFIRMED],
            'atendido' => ['attend', AppointmentStatus::CHECKED_IN],
            'ausente' => ['no-show', AppointmentStatus::CONFIRMED],
            'cancelar' => ['cancel', AppointmentStatus::CONFIRMED],
        ];
    }

    public function test_undoing_a_no_show_locks_veterinarian_schedule_and_appointment_before_the_overlap(): void
    {
        $schedule = $this->openSchedule($this->defaultBranchIn($this->createClinic()));
        $id = $this->absent($schedule);
        Sanctum::actingAs($this->userWithRoles($schedule->clinic, ['recepcionista']));

        $queries = $this->queriesOf(fn () => $this->transition($id, 'undo-no-show')->assertOk());

        $this->assertBefore($queries, $this->lockOf('veterinarians'), $this->lockOf('schedules'), 'La agenda se bloqueó antes que el veterinario.');
        $this->assertBefore($queries, $this->lockOf('schedules'), $this->lockOf('appointments'), 'El turno se bloqueó antes que la agenda.');

        $lock = $this->firstIndex($queries, $this->lockOf('appointments'));
        $overlap = $this->firstIndex($queries, fn (string $sql) => preg_match('/status[`"] in \(/', $sql) === 1 && ($this->readOf('appointments'))($sql));
        $this->assertNotNull($overlap, "No se revisó la superposición:\n" . implode("\n", $queries));
        $this->assertGreaterThan($lock, $overlap, 'Se revisó la superposición antes de bloquear el turno.');
    }

    public function test_undoing_is_retried_if_the_veterinarian_of_the_schedule_changes_while_locking(): void
    {
        $branch = $this->defaultBranchIn($this->createClinic());
        $schedule = $this->openSchedule($branch);
        $changed = $this->veterinarianIn($branch);
        $id = $this->absent($schedule);
        Sanctum::actingAs($this->userWithRoles($schedule->clinic, ['recepcionista']));

        $reads = (object) ['count' => 0];
        DB::listen(function ($query) use ($schedule, $changed, $reads) {
            if (preg_match('/^select [`"]id[`"], [`"]veterinarian_id[`"] from [`"]schedules[`"]/i', $query->sql) === 1 && ++$reads->count === 1) {
                DB::table('schedules')->where('id', $schedule->id)->update(['veterinarian_id' => $changed->id]);
            }
        });

        $this->transition($id, 'undo-no-show')->assertOk();
        $this->assertSame(2, $reads->count);
    }

    /**
     * Un deadlock se repite hasta AppointmentService::ATTEMPTS y responde 409; una espera vencida
     * responde 409 al primer intento. El turno no cambia.
     */
    public function test_a_deadlock_is_retried_and_a_lock_wait_timeout_is_not(): void
    {
        $schedule = $this->openSchedule($this->defaultBranchIn($this->createClinic()));
        $id = $this->appointmentIn($schedule, $this->today('10:00'));
        $this->actingIn($schedule->clinic, self::TRANSITION_PERMISSIONS);

        $failures = $this->failOnAppointmentLock(self::DEADLOCK);
        $response = $this->transition($id, 'cancel', ['cancellation_reason' => 'Motivo']);
        $this->assertErrorCode($response, 409, 'CONFLICT');
        $this->assertSame(AppointmentService::ATTEMPTS, $failures->count);

        $failures->message = self::LOCK_WAIT_TIMEOUT;
        $failures->count = 0;
        $this->assertErrorCode($this->transition($id, 'check-in'), 409, 'CONFLICT');
        $this->assertSame(1, $failures->count);

        $this->assertSame('confirmed', $this->appointmentRow($id)->status);
    }

    private function absent(\App\Models\Schedule $schedule): int
    {
        $id = $this->appointmentIn($schedule, $this->today('10:00'));
        $this->setStatus($id, AppointmentStatus::NO_SHOW, ['no_show_at' => self::TODAY . ' 10:00:00']);

        return $id;
    }

    private function failOnAppointmentLock(string $message): object
    {
        $failures = (object) ['count' => 0, 'message' => $message];

        DB::listen(function ($query) use ($failures) {
            if (! ($this->lockOf('appointments'))(strtolower($query->sql))) {
                return;
            }

            $failures->count++;

            throw new QueryException($query->connectionName, $query->sql, $query->bindings, new PDOException($failures->message));
        });

        return $failures;
    }
}
