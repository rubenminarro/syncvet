<?php

namespace Tests\Feature\Appointments;

use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Concerns\InteractsWithAppointments;
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
 *
 * Los procesos aparte no ven la transacción de RefreshDatabase: este test confirma sus datos
 * ($connectionsToTransact vacío) y los borra al terminar (los de su clínica).
 */
class AppointmentConcurrencyTest extends TestCase
{
    use InteractsWithAppointments;

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
        // Reloj real: los procesos aparte no ven el reloj congelado de un test.
        $startsAt = now()->addDays(2)->format('Y-m-d') . 'T10:00:00';
        $tokens = [
            $this->userWithRoles($clinic, ['recepcionista'])->createToken('a')->plainTextToken,
            $this->userWithRoles($clinic, ['recepcionista'])->createToken('b')->plainTextToken,
        ];

        $holder = $this->secondConnection();
        $holder->beginTransaction();
        $holder->select('select id from veterinarians where id = ? for update', [$schedule->veterinarian_id]);

        $processes = [];

        try {
            foreach ($tokens as $index => $token) {
                $processes[] = $this->startRequest($token, [
                    'schedule_id' => $schedule->id,
                    'starts_at' => $startsAt,
                    'contact_name' => "Contacto {$index}",
                    'contact_phone' => '981123456',
                ]);
            }

            $this->waitUntilLockWaits(DB::connection(), 2, $processes);
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

    /**
     * @param array<string, mixed> $body
     */
    private function startRequest(string $token, array $body): Process
    {
        $process = new Process(
            [PHP_BINARY, base_path('tests/Support/concurrent-request.php'), 'POST', '/api/appointments', $token, json_encode($body)],
            base_path(),
            [
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'mysql',
                'DB_DATABASE' => config('database.connections.mysql.database'),
                'DB_URL' => '',
                'CACHE_STORE' => 'array',
                'LOG_CHANNEL' => 'null',
                'QUEUE_CONNECTION' => 'sync',
                'SESSION_DRIVER' => 'array',
                'EMAIL_DNS_CHECK' => 'false',
                'XDEBUG_MODE' => 'off',
            ],
            null,
            60
        );
        $process->start();

        return $process;
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function resultOf(Process $process): array
    {
        $process->wait();
        $result = json_decode($process->getOutput(), true);

        $this->assertIsArray($result, "El proceso no respondió JSON:\n" . $process->getOutput() . $process->getErrorOutput());

        return $result;
    }

    /**
     * Espera (hasta 30 segundos) a que $count pedidos estén esperando el bloqueo del veterinario.
     *
     * @param list<Process> $processes
     */
    private function waitUntilLockWaits(Connection $connection, int $count, array $processes): void
    {
        $deadline = microtime(true) + 30;

        while (microtime(true) < $deadline) {
            // Las consultas de los otros procesos que siguen en el SELECT … FOR UPDATE del
            // veterinario: con la fila tomada por el test, están esperando ese bloqueo.
            $waiting = (int) $connection->selectOne(
                'select count(*) as total from information_schema.processlist where db = database() and id <> connection_id() and info like ?',
                ['select * from `veterinarians` where %for update']
            )->total;

            if ($waiting >= $count) {
                return;
            }

            foreach ($processes as $process) {
                if (! $process->isRunning()) {
                    $this->fail("Un pedido terminó sin esperar el bloqueo del veterinario:\n" . $process->getOutput() . $process->getErrorOutput());
                }
            }

            usleep(50_000);
        }

        $processList = $connection->select('select id, command, time, state, info from information_schema.processlist where db = database()');

        $this->fail("Los dos pedidos no llegaron a esperar el bloqueo del veterinario en 30 segundos.\n"
            . json_encode($processList, JSON_PRETTY_PRINT) . "\n"
            . implode("\n", array_map(fn (Process $process) => $process->getIncrementalOutput() . $process->getIncrementalErrorOutput(), $processes)));
    }

    private function secondConnection(): Connection
    {
        config(['database.connections.mysql_concurrency' => config('database.connections.mysql')]);

        return DB::connection('mysql_concurrency');
    }

    /**
     * Borra los datos de la clínica del test (confirmados, sin la transacción de
     * RefreshDatabase): cada tabla con clinic_id, los roles, tokens y sucursales de sus
     * usuarios y la clínica.
     */
    private function deleteClinicData(int $clinicId): void
    {
        $db = DB::connection();
        $userIds = $db->table('users')->where('clinic_id', $clinicId)->pluck('id')->all();
        $tables = array_column($db->select(
            "select table_name as name from information_schema.columns where table_schema = database() and column_name = 'clinic_id'"
        ), 'name');

        $db->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            $morphClass = (new User())->getMorphClass();
            $db->table('personal_access_tokens')->where('tokenable_type', $morphClass)->whereIn('tokenable_id', $userIds)->delete();
            $db->table('model_has_roles')->where('model_type', $morphClass)->whereIn('model_id', $userIds)->delete();
            $db->table('model_has_permissions')->where('model_type', $morphClass)->whereIn('model_id', $userIds)->delete();
            $db->table('branch_user')->whereIn('user_id', $userIds)->delete();

            foreach ($tables as $table) {
                $db->table($table)->where('clinic_id', $clinicId)->delete();
            }

            $db->table('clinics')->where('id', $clinicId)->delete();
        } finally {
            $db->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
