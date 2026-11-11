<?php

namespace Tests\Feature\Appointments\Series;

use App\Enums\AuditEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\Concerns\InteractsWithAppointments;
use Tests\Concerns\RunsConcurrentRequests;
use Tests\TestCase;

/**
 * Un deadlock a mitad de la serie (FASE 3, entrega 3.3; revisión, hallazgo 3; solo MySQL): MySQL
 * deshace la transacción entera, AppointmentService::withRetries la repite y la serie queda una
 * sola vez, con N turnos y un solo series_id; nada del intento fallido.
 *
 * Sin la transacción de RefreshDatabase, para que la transacción de la serie sea la de verdad (no
 * un savepoint): borra al final los datos de su clínica.
 */
class AppointmentSeriesRetryTest extends TestCase
{
    use InteractsWithAppointments;
    use RunsConcurrentRequests;

    private const DEADLOCK = 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction';

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
            $this->markTestSkipped('Solo en MySQL: la transacción de la serie, sin la de RefreshDatabase.');
        }

        $this->freezeToday();
    }

    protected function tearDown(): void
    {
        if ($this->clinicId !== null) {
            $this->deleteClinicData($this->clinicId);
        }

        parent::tearDown();
    }

    public function test_a_deadlock_in_the_middle_of_the_series_is_retried_and_leaves_the_series_once(): void
    {
        $clinic = $this->createClinic();
        $this->clinicId = $clinic->id;
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);

        // El tercer insert del primer intento (después de insertar dos turnos) falla con un
        // deadlock; el segundo intento entra entero.
        $inserts = (object) ['count' => 0];
        DB::listen(function ($query) use ($inserts) {
            if (preg_match('/^insert into `appointments`/', $query->sql) === 1 && ++$inserts->count === 3) {
                throw new QueryException($query->connectionName, $query->sql, $query->bindings, new PDOException(self::DEADLOCK));
            }
        });

        $response = $this->bookSeries($schedule, ['count' => 4])->assertCreated()->assertJsonCount(4, 'data');

        // Tres del intento fallido (dos deshechos y el que falló) y cuatro del que entró.
        $this->assertSame(7, $inserts->count);

        $rows = DB::table('appointments')->where('clinic_id', $clinic->id)->orderBy('starts_at')->get();
        $this->assertCount(4, $rows);
        $this->assertSame([$response->json('meta.series_id')], $rows->pluck('series_id')->unique()->values()->all());
        $this->assertSame($response->json('data.*.id'), $rows->pluck('id')->all());
        $this->assertSame(
            ['2026-11-16 10:00:00', '2026-11-23 10:00:00', '2026-11-30 10:00:00', '2026-12-07 10:00:00'],
            $rows->pluck('starts_at')->all()
        );

        // La auditoría, solo de los cuatro que quedaron.
        $this->assertSame(
            $rows->pluck('id')->sort()->values()->all(),
            DB::table('audit_logs')->where('clinic_id', $clinic->id)->where('auditable_type', 'appointment')
                ->where('event', AuditEvent::CREATED->value)->orderBy('auditable_id')->pluck('auditable_id')->all()
        );
    }
}
