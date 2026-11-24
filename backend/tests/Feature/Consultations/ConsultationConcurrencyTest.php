<?php

namespace Tests\Feature\Consultations;

use App\Enums\AppointmentStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Concerns\InteractsWithConsultations;
use Tests\Concerns\RunsConcurrentRequests;
use Tests\TestCase;

/**
 * Dos aperturas simultáneas de la consulta del mismo turno (FASE 4, entrega 4.1; solo MySQL): una
 * entra (201, el turno pasa a atendido) y la otra, con el turno ya atendido y con consulta,
 * recibe 409 CONSULTATION_ALREADY_EXISTS, nunca un 500 ni una segunda consulta.
 *
 * Dos procesos de PHP aparte piden las dos aperturas mientras una tercera conexión del test tiene
 * tomada la fila del turno (FOR UPDATE): los dos quedan esperando ese bloqueo (lo que prueba que
 * abrir bloquea el turno antes de revisar su estado y su consulta) y, al soltarlo, uno entra y el
 * otro ve lo que dejó el primero. Sin la transacción de RefreshDatabase: borra al final los datos
 * de su clínica.
 */
class ConsultationConcurrencyTest extends TestCase
{
    use InteractsWithConsultations;
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

    public function test_two_simultaneous_openings_of_the_same_appointment_one_wins_and_the_other_gets_409(): void
    {
        $clinic = $this->createClinic();
        $this->clinicId = $clinic->id;
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        // Reloj real (los procesos aparte no ven uno congelado): hoy a las 00:00 de Asunción,
        // fichado a esa hora.
        $start = now('America/Asuncion')->startOfDay();
        $id = $this->appointmentIn($schedule, $start, AppointmentStatus::CHECKED_IN);
        DB::table('appointments')->where('id', $id)->update(['checked_in_at' => $start->format('Y-m-d H:i:s')]);
        $user = DB::table('veterinarians')->where('id', $schedule->veterinarian_id)->value('user_id');
        $vet = \App\Models\User::findOrFail($user);
        $tokens = [$vet->createToken('a')->plainTextToken, $vet->createToken('b')->plainTextToken];

        $holder = $this->secondConnection();
        $holder->beginTransaction();
        $holder->select('select id from appointments where id = ? for update', [$id]);

        $processes = [];

        try {
            foreach ($tokens as $token) {
                $processes[] = $this->startRequest($token, 'POST', '/api/consultations', ['appointment_id' => $id]);
            }

            $this->waitUntilLockWaits(DB::connection(), 2, $processes, 'select * from `appointments` where %for update');
        } finally {
            $holder->rollBack();
        }

        $results = array_map(fn (Process $process) => $this->resultOf($process), $processes);
        $statuses = array_column($results, 'status');
        sort($statuses);

        $this->assertSame([201, 409], $statuses, json_encode($results, JSON_UNESCAPED_UNICODE));
        $loser = $results[0]['status'] === 409 ? $results[0] : $results[1];
        $this->assertSame('CONSULTATION_ALREADY_EXISTS', $loser['body']['error_code'] ?? null, json_encode($loser, JSON_UNESCAPED_UNICODE));
        $this->assertSame(1, DB::table('consultations')->where('appointment_id', $id)->count());
        $this->assertSame('attended', DB::table('appointments')->where('id', $id)->value('status'));
    }
}
