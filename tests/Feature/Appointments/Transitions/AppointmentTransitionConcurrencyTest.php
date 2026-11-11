<?php

namespace Tests\Feature\Appointments\Transitions;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Concerns\InteractsWithAppointments;
use Tests\Concerns\RunsConcurrentRequests;
use Tests\TestCase;

/**
 * Dos transiciones simultáneas sobre el mismo turno (FASE 3, entrega 3.2b; solo MySQL): una
 * entra (200) y la otra ve el estado nuevo y recibe 422 APPOINTMENT_INVALID_TRANSITION, nunca un
 * 500. Fichar y marcar ausente se excluyen en cualquier orden (un fichado no pasa a ausente ni un
 * ausente a fichado).
 *
 * Dos procesos de PHP aparte piden las dos transiciones mientras una tercera conexión del test
 * tiene tomada la fila del turno (FOR UPDATE): los dos quedan esperando ese bloqueo (lo que
 * prueba que cada transición bloquea el turno antes de leer su estado) y, al soltarlo, uno entra
 * y el otro lee el estado nuevo. Sin la transacción de RefreshDatabase: borra al final los datos
 * de su clínica.
 */
class AppointmentTransitionConcurrencyTest extends TestCase
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

    public function test_two_simultaneous_transitions_one_wins_and_the_other_gets_422(): void
    {
        $clinic = $this->createClinic();
        $this->clinicId = $clinic->id;
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        // Reloj real (los procesos aparte no ven uno congelado): hoy a las 00:00 de Asunción, así
        // que es del día (se ficha) y ya empezó (se marca ausente).
        $id = $this->appointmentIn($schedule, now('America/Asuncion')->startOfDay());
        $tokens = [
            'check-in' => $this->userWithRoles($clinic, ['recepcionista'])->createToken('a')->plainTextToken,
            'no-show' => $this->userWithRoles($clinic, ['recepcionista'])->createToken('b')->plainTextToken,
        ];

        $holder = $this->secondConnection();
        $holder->beginTransaction();
        $holder->select('select id from appointments where id = ? for update', [$id]);

        $processes = [];

        try {
            foreach ($tokens as $action => $token) {
                $processes[$action] = $this->startRequest($token, 'POST', "/api/appointments/{$id}/{$action}");
            }

            $this->waitUntilLockWaits(DB::connection(), 2, array_values($processes), 'select * from `appointments` where %for update');
        } finally {
            $holder->rollBack();
        }

        $results = array_map(fn (Process $process) => $this->resultOf($process), $processes);
        $statuses = array_column($results, 'status');
        sort($statuses);

        $this->assertSame([200, 422], $statuses, json_encode($results, JSON_UNESCAPED_UNICODE));

        $winner = $results['check-in']['status'] === 200 ? 'check-in' : 'no-show';
        $loser = $winner === 'check-in' ? 'no-show' : 'check-in';
        $this->assertSame('APPOINTMENT_INVALID_TRANSITION', $results[$loser]['body']['error_code']);
        $this->assertSame($winner === 'check-in' ? 'checked_in' : 'no_show', DB::table('appointments')->where('id', $id)->value('status'));
    }
}
