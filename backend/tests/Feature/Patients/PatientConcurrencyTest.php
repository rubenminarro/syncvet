<?php

namespace Tests\Feature\Patients;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Concerns\InteractsWithAuth;
use Tests\Concerns\RunsConcurrentRequests;
use Tests\TestCase;

/**
 * Dos altas simultáneas con el mismo microchip (deuda 22: daba 500): una entra y la otra recibe
 * 422 en microchip, nunca 500. Solo MySQL.
 *
 * Dos procesos de PHP aparte (tests/Support/concurrent-request.php) piden la misma alta. Para que
 * las dos pasen la validación y lleguen juntas a escribir, una tercera conexión del test tiene
 * tomada la fila de la serie de pacientes (document_sequences) con un SELECT … FOR UPDATE: el
 * alta la bloquea antes del INSERT (PatientService, la serie siempre última), así que los dos
 * pedidos esperan ese bloqueo (el test los ve en information_schema.processlist) y, al soltarlo,
 * uno inserta y confirma y el otro choca con el índice del microchip.
 *
 * Los procesos aparte no ven la transacción de RefreshDatabase: este test confirma sus datos
 * ($connectionsToTransact vacío) y los borra al terminar (los de su clínica).
 */
class PatientConcurrencyTest extends TestCase
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

    public function test_two_simultaneous_creations_with_the_same_microchip_one_wins_and_the_other_gets_422(): void
    {
        $clinic = $this->createClinic();
        $this->clinicId = $clinic->id;
        $owner = $this->clientIn($clinic);
        $microchip = '985112003456789';
        $tokens = [
            $this->userWithRole($clinic, 'recepcionista')->createToken('a')->plainTextToken,
            $this->userWithRole($clinic, 'recepcionista')->createToken('b')->plainTextToken,
        ];

        // La fila de la serie tiene que existir para tomarla (se crea en el primer uso).
        DB::table('document_sequences')->insert([
            'clinic_id' => $clinic->id,
            'key' => 'patient',
            'next_value' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $holder = $this->secondConnection();
        $holder->beginTransaction();
        $holder->select(
            'select id from document_sequences where clinic_id = ? and `key` = ? for update',
            [$clinic->id, 'patient']
        );

        $processes = [];

        try {
            foreach ($tokens as $index => $token) {
                $processes[] = $this->startRequest($token, 'POST', "/api/clients/{$owner->id}/patients", $this->patientPayload([
                    'name' => "Alta {$index}",
                    'microchip' => $microchip,
                ]));
            }

            $this->waitUntilLockWaits(DB::connection(), 2, $processes, 'select * from `document_sequences`%');
        } finally {
            $holder->rollBack();
        }

        $results = array_map(fn (Process $process) => $this->resultOf($process), $processes);
        $statuses = array_column($results, 'status');
        sort($statuses);

        $this->assertSame([201, 422], $statuses, json_encode($results, JSON_UNESCAPED_UNICODE));

        $rejected = $results[0]['status'] === 422 ? $results[0] : $results[1];
        $this->assertSame('VALIDATION_ERROR', $rejected['body']['error_code']);
        $this->assertSame(['Este microchip ya está registrado en otro paciente.'], $rejected['body']['errors']['microchip']);
        $this->assertSame(1, DB::table('patients')->where('clinic_id', $clinic->id)->count());
    }
}
