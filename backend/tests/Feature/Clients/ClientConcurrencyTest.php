<?php

namespace Tests\Feature\Clients;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Concerns\InteractsWithAuth;
use Tests\Concerns\RunsConcurrentRequests;
use Tests\TestCase;

/**
 * Dos altas simultáneas del mismo documento (revisión de la entrega 1.2 del frontend: el doble
 * clic en Guardar daba 500): una entra y la otra recibe 422 en document_number, nunca 500. Solo
 * MySQL.
 *
 * Dos procesos de PHP aparte (tests/Support/concurrent-request.php) piden la misma alta. Para que
 * las dos pasen la validación y lleguen juntas al INSERT, una tercera conexión del test tiene
 * tomado el hueco de ese documento en el índice único (clinic_id, document_number) con un
 * SELECT … FOR UPDATE de un documento que todavía no existe: los dos INSERT esperan ese bloqueo
 * (el test los ve en information_schema.processlist) y, al soltarlo, uno inserta y el otro choca
 * con el índice.
 *
 * Los procesos aparte no ven la transacción de RefreshDatabase: este test confirma sus datos
 * ($connectionsToTransact vacío) y los borra al terminar (los de su clínica).
 */
class ClientConcurrencyTest extends TestCase
{
    use InteractsWithAuth;
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

    public function test_two_simultaneous_creations_of_the_same_document_one_wins_and_the_other_gets_422(): void
    {
        $clinic = $this->createClinic();
        $this->clinicId = $clinic->id;
        $document = '99400001';
        $tokens = [
            $this->userWithRole($clinic, 'recepcionista')->createToken('a')->plainTextToken,
            $this->userWithRole($clinic, 'recepcionista')->createToken('b')->plainTextToken,
        ];

        $holder = $this->secondConnection();
        $holder->beginTransaction();
        // El documento no existe: el FOR UPDATE toma el hueco del índice único donde iría.
        $holder->select(
            'select id from clients where clinic_id = ? and document_number = ? for update',
            [$clinic->id, $document]
        );

        $processes = [];

        try {
            foreach ($tokens as $index => $token) {
                // Distinto email: el choque es solo el del documento.
                $processes[] = $this->startRequest($token, 'POST', '/api/clients', $this->clientPayload([
                    'document_number' => $document,
                    'email' => "alta{$index}@example.com",
                ]));
            }

            $this->waitUntilLockWaits(DB::connection(), 2, $processes, 'insert into `clients`%');
        } finally {
            $holder->rollBack();
        }

        $results = array_map(fn (Process $process) => $this->resultOf($process), $processes);
        $statuses = array_column($results, 'status');
        sort($statuses);

        $this->assertSame([201, 422], $statuses, json_encode($results, JSON_UNESCAPED_UNICODE));

        $rejected = $results[0]['status'] === 422 ? $results[0] : $results[1];
        $this->assertSame('VALIDATION_ERROR', $rejected['body']['error_code']);
        $this->assertSame(['Este número de documento ya está en uso.'], $rejected['body']['errors']['document_number']);
        $this->assertSame(1, DB::table('clients')->where('clinic_id', $clinic->id)->count());
    }
}
