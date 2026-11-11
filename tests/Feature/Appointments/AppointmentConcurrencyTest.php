<?php

namespace Tests\Feature\Appointments;

use App\Models\Clinic;
use App\Models\Schedule;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Concerns\InteractsWithAppointments;
use Tests\Concerns\RunsConcurrentRequests;
use Tests\TestCase;

/**
 * Criterio de cierre de la FASE 3 (entrega 3.2a): dos altas simultáneas en el mismo horario,
 * una entra y la otra recibe 409 APPOINTMENT_OVERLAP. Solo MySQL.
 *
 * Dos procesos de PHP aparte (tests/Support/concurrent-request.php), cada uno con su conexión,
 * piden el mismo turno por la API. Para que corran de verdad a la vez, una tercera conexión
 * del test tiene tomada la fila del veterinario (FOR UPDATE): los dos pedidos quedan esperando
 * ese bloqueo (el test lo ve en information_schema.innodb_trx) y, al soltarlo, uno bloquea el
 * veterinario y la agenda, inserta y confirma; el otro lee la superposición después y recibe
 * 409. Que los dos esperen prueba además que el alta pide el bloqueo antes de leer el horario.
 * Con una agenda solo de servicio (sin veterinario), la conexión del test toma el servicio y lo
 * que ordena las dos altas es solo el bloqueo de la agenda (revisión de la 3.2a, hallazgo 1).
 *
 * Los procesos aparte no ven la transacción de RefreshDatabase: este test confirma sus datos
 * ($connectionsToTransact vacío) y los borra al terminar (los de su clínica).
 */
class AppointmentConcurrencyTest extends TestCase
{
    use InteractsWithAppointments;
    use RunsConcurrentRequests;

    /**
     * Sin la transacción de RefreshDatabase.
     *
     * @var list<string>
     */
    protected array $connectionsToTransact = [];

    private ?int $clinicId = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Solo en MySQL: dos procesos con sus propias conexiones.');
        }
    }

    protected function tearDown(): void
    {
        if ($this->clinicId !== null) {
            $this->deleteClinicData($this->clinicId);
        }

        parent::tearDown();
    }

    public function test_two_simultaneous_bookings_of_the_same_slot_one_wins_and_the_other_gets_409(): void
    {
        $clinic = $this->createClinic();
        $this->clinicId = $clinic->id;
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));

        $this->assertOneWinsAndTheOtherGets409(
            $clinic,
            $schedule,
            'select id from veterinarians where id = ? for update',
            $schedule->veterinarian_id,
            'select * from `veterinarians` where %for update'
        );
    }

    /**
     * Revisión de la 3.2a (hallazgo 1): en una agenda solo de servicio, sin veterinario, el
     * bloqueo de la agenda es lo único que ordena las dos altas. La tercera conexión toma el
     * servicio (FOR UPDATE): los dos pedidos esperan su sharedLock y, al soltarlo, lo obtienen
     * a la vez; sin el lockForUpdate de la agenda, los dos leen el horario libre y responden 201.
     */
    public function test_in_a_schedule_without_a_veterinarian_the_schedule_lock_orders_the_bookings(): void
    {
        $clinic = $this->createClinic();
        $this->clinicId = $clinic->id;
        $service = $this->serviceIn($clinic);
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic), ['veterinarian_id' => null, 'service_id' => $service->id]);

        $this->assertOneWinsAndTheOtherGets409(
            $clinic,
            $schedule,
            'select id from services where id = ? for update',
            $service->id,
            'select * from `services` where %lock in share mode'
        );
    }

    /**
     * Los dos pedidos piden el mismo turno mientras una tercera conexión tiene tomada la fila
     * ($lockSql con $lockedId); el test espera a verlos a los dos en la consulta $waitingLike,
     * suelta el bloqueo y verifica un 201 y un 409 APPOINTMENT_OVERLAP.
     */
    private function assertOneWinsAndTheOtherGets409(Clinic $clinic, Schedule $schedule, string $lockSql, int $lockedId, string $waitingLike): void
    {
        // Reloj real: los procesos aparte no ven el reloj congelado de un test.
        $startsAt = now()->addDays(2)->format('Y-m-d') . 'T10:00:00';
        $tokens = [
            $this->userWithRoles($clinic, ['recepcionista'])->createToken('a')->plainTextToken,
            $this->userWithRoles($clinic, ['recepcionista'])->createToken('b')->plainTextToken,
        ];

        $holder = $this->secondConnection();
        $holder->beginTransaction();
        $holder->select($lockSql, [$lockedId]);

        $processes = [];

        try {
            foreach ($tokens as $index => $token) {
                $processes[] = $this->startRequest($token, 'POST', '/api/appointments', [
                    'schedule_id' => $schedule->id,
                    'starts_at' => $startsAt,
                    'contact_name' => "Contacto {$index}",
                    'contact_phone' => '981123456',
                ]);
            }

            $this->waitUntilLockWaits(DB::connection(), 2, $processes, $waitingLike);
        } finally {
            $holder->rollBack();
        }

        $results = array_map(fn (Process $process) => $this->resultOf($process), $processes);
        $statuses = array_column($results, 'status');
        sort($statuses);

        $this->assertSame([201, 409], $statuses, json_encode($results, JSON_UNESCAPED_UNICODE));

        $rejected = $results[0]['status'] === 409 ? $results[0] : $results[1];
        $this->assertSame('APPOINTMENT_OVERLAP', $rejected['body']['error_code']);
        $this->assertSame(1, DB::table('appointments')->where('schedule_id', $schedule->id)->count());
    }
}
