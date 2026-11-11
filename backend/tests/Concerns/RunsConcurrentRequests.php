<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Pedidos simultáneos de verdad (FASE 3, entregas 3.2a y 3.2b; solo MySQL): procesos de PHP aparte
 * que piden a la API con su propia conexión, una conexión del test que tiene tomada una fila para
 * que los pedidos esperen juntos, y la limpieza de los datos confirmados de la clínica del test
 * (estos tests no usan la transacción de RefreshDatabase: $connectionsToTransact vacío).
 */
trait RunsConcurrentRequests
{
    /**
     * Un pedido a la API en un proceso de PHP aparte (tests/Support/concurrent-request.php),
     * con su propia conexión a MySQL; empieza enseguida y no espera la respuesta.
     *
     * @param array<string, mixed> $body
     */
    protected function startRequest(string $token, string $method, string $uri, array $body = []): Process
    {
        $process = new Process(
            [PHP_BINARY, base_path('tests/Support/concurrent-request.php'), $method, $uri, $token, json_encode($body)],
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
    protected function resultOf(Process $process): array
    {
        $process->wait();
        $result = json_decode($process->getOutput(), true);

        $this->assertIsArray($result, "El proceso no respondió JSON:\n" . $process->getOutput() . $process->getErrorOutput());

        return $result;
    }

    /**
     * Espera (hasta 30 segundos) a que $count pedidos estén esperando el bloqueo de la fila tomada.
     *
     * @param list<Process> $processes
     */
    protected function waitUntilLockWaits(Connection $connection, int $count, array $processes, string $waitingLike): void
    {
        $deadline = microtime(true) + 30;

        while (microtime(true) < $deadline) {
            // Las consultas de los otros procesos que siguen en la lectura con bloqueo de la
            // fila tomada por el test: están esperando ese bloqueo.
            $waiting = (int) $connection->selectOne(
                'select count(*) as total from information_schema.processlist where db = database() and id <> connection_id() and info like ?',
                [$waitingLike]
            )->total;

            if ($waiting >= $count) {
                return;
            }

            foreach ($processes as $process) {
                if (! $process->isRunning()) {
                    $this->fail("Un pedido terminó sin esperar el bloqueo:\n" . $process->getOutput() . $process->getErrorOutput());
                }
            }

            usleep(50_000);
        }

        $processList = $connection->select('select id, command, time, state, info from information_schema.processlist where db = database()');

        $this->fail("Los dos pedidos no llegaron a esperar el bloqueo en 30 segundos.\n"
            . json_encode($processList, JSON_PRETTY_PRINT) . "\n"
            . implode("\n", array_map(fn (Process $process) => $process->getIncrementalOutput() . $process->getIncrementalErrorOutput(), $processes)));
    }

    /**
     * Una conexión aparte a la misma base, para tomar una fila con FOR UPDATE mientras los
     * procesos esperan.
     */
    protected function secondConnection(): Connection
    {
        config(['database.connections.mysql_concurrency' => config('database.connections.mysql')]);

        return DB::connection('mysql_concurrency');
    }

    /**
     * Borra los datos de la clínica del test (confirmados, sin la transacción de
     * RefreshDatabase): cada tabla con clinic_id, los roles, tokens y sucursales de sus
     * usuarios y la clínica.
     */
    protected function deleteClinicData(int $clinicId): void
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
