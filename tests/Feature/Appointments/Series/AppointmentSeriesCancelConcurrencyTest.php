<?php

namespace Tests\Feature\Appointments\Series;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Concerns\InteractsWithAppointments;
use Tests\Concerns\RunsConcurrentRequests;
use Tests\TestCase;

/**
 * Dos cancelaciones simultáneas de la misma serie (FASE 3, entrega 3.3; revisión, hallazgo 4; solo
 * MySQL): series desde el segundo turno y following desde el primero. Cualquiera de las dos cancela
 * los cuatro turnos, incluido el que pide la otra: la que entra responde 200 con
 * meta.cancelled_count 4 y la otra ve su turno cancelado y responde 422
 * APPOINTMENT_INVALID_TRANSITION, nunca un 500.
 *
 * Dos procesos de PHP aparte piden las cancelaciones mientras una tercera conexión del test tiene
 * tomado el primer turno de la serie (FOR UPDATE): los dos quedan esperando ese bloqueo (lo que
 * prueba que la cancelación bloquea los turnos de la serie antes de leer sus estados) y, al
 * soltarlo, uno entra y el otro lee los estados nuevos. Sin la transacción de RefreshDatabase:
 * borra al final los datos de su clínica.
 */
class AppointmentSeriesCancelConcurrencyTest extends TestCase
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

    public function test_two_simultaneous_cancellations_of_the_same_series(): void
    {
        $clinic = $this->createClinic();
        $this->clinicId = $clinic->id;
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);

        // Reloj real (los procesos aparte no ven uno congelado): cuatro semanas desde pasado mañana.
        $ids = $this->bookedSeries($schedule, ['count' => 4], [
            'starts_at' => now('America/Asuncion')->addDays(2)->setTime(10, 0)->format('Y-m-d\TH:i:s'),
        ]);
        $requests = [
            'series' => [$ids[1], 'series'],
            'following' => [$ids[0], 'following'],
        ];

        $holder = $this->secondConnection();
        $holder->beginTransaction();
        $holder->select('select id from appointments where id = ? for update', [min($ids)]);

        $processes = [];

        try {
            foreach ($requests as $name => [$id, $scope]) {
                $token = $this->userWithRoles($clinic, ['recepcionista'])->createToken($name)->plainTextToken;
                $processes[$name] = $this->startRequest($token, 'POST', "/api/appointments/{$id}/cancel", [
                    'cancellation_reason' => "Cancelación {$name}",
                    'scope' => $scope,
                ]);
            }

            $this->waitUntilLockWaits(DB::connection(), 2, array_values($processes), 'select * from `appointments` where `series_id` = %for update');
        } finally {
            $holder->rollBack();
        }

        $results = array_map(fn (Process $process) => $this->resultOf($process), $processes);
        $statuses = array_column($results, 'status');
        sort($statuses);

        $this->assertSame([200, 422], $statuses, json_encode($results, JSON_UNESCAPED_UNICODE));

        $winner = $results['series']['status'] === 200 ? 'series' : 'following';
        $loser = $winner === 'series' ? 'following' : 'series';
        $this->assertSame(4, $results[$winner]['body']['meta']['cancelled_count']);
        $this->assertSame('APPOINTMENT_INVALID_TRANSITION', $results[$loser]['body']['error_code']);

        // Los cuatro, cancelados una sola vez, con el motivo de la que entró.
        $rows = DB::table('appointments')->whereIn('id', $ids)->get();
        $this->assertSame(['cancelled'], $rows->pluck('status')->unique()->values()->all());
        $this->assertSame(["Cancelación {$winner}"], $rows->pluck('cancellation_reason')->unique()->values()->all());
        $this->assertSame(4, DB::table('audit_logs')->where('clinic_id', $clinic->id)->where('auditable_type', 'appointment')
            ->where('event', 'updated')->count());
    }
}
