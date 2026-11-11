<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithBranches;
use Tests\Concerns\InteractsWithClinics;
use Tests\TestCase;

/**
 * Deuda 16 (FASE 3, entrega 3.0; R-047 y R-049): la sesión de MySQL tiene una zona fija
 * (`timezone` de la conexión, `DB_TIMEZONE`, -03:00 por defecto), así que las columnas
 * `timestamp` no dependen de la zona del servidor. Solo en MySQL: SQLite no tiene zona.
 */
class MySqlSessionTimezoneTest extends TestCase
{
    use InteractsWithBranches;
    use InteractsWithClinics;

    /**
     * Conexión de prueba, copia de la mysql: reconectar la de los tests cortaría la
     * transacción de RefreshDatabase.
     */
    private const PROBE = 'mysql_timezone_probe';

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('La zona de la sesión es de MySQL: se verifica en MySQL.');
        }
    }

    protected function tearDown(): void
    {
        DB::purge(self::PROBE);

        parent::tearDown();
    }

    public function test_the_session_uses_the_configured_offset(): void
    {
        $this->assertSame('-03:00', config('database.connections.mysql.timezone'));
        $this->assertSame('-03:00', DB::scalar('SELECT @@session.time_zone'));
    }

    public function test_the_offset_survives_a_reconnection(): void
    {
        config(['database.connections.' . self::PROBE => config('database.connections.mysql')]);
        $probe = DB::connection(self::PROBE);

        // Otra zona en la sesión, como la de un servidor en otra zona.
        $probe->statement("SET time_zone = '+05:00'");
        $this->assertSame('+05:00', $probe->scalar('SELECT @@session.time_zone'));

        DB::reconnect(self::PROBE);

        $this->assertSame('-03:00', DB::connection(self::PROBE)->scalar('SELECT @@session.time_zone'));
    }

    public function test_a_timestamp_keeps_its_instant(): void
    {
        $instant = Carbon::parse('2026-07-01T12:00:00Z');
        $patient = $this->patientOf($this->clientIn($this->createClinic()), ['deceased_at' => '2026-07-01T12:00:00Z']);

        $row = DB::selectOne(
            'SELECT deceased_at, UNIX_TIMESTAMP(deceased_at) AS epoch FROM patients WHERE id = ?',
            [$patient->id],
        );

        // AppDateTime guarda la hora de Asunción y MySQL la convierte con la zona de la sesión.
        $this->assertSame('2026-07-01 09:00:00', $row->deceased_at);
        $this->assertSame($instant->getTimestamp(), (int) $row->epoch);

        $read = $patient->fresh()->deceased_at;
        $this->assertTrue($read->equalTo($instant));
        $this->assertSame('2026-07-01T12:00:00.000000Z', $read->toISOString());
    }

    public function test_an_old_daylight_saving_date_keeps_its_instant_in_php(): void
    {
        // El 01/07/2023 Asunción estaba en UTC-4 (horario de invierno, antes de quedar en
        // UTC-3 todo el año): AppDateTime guarda 08:00:00 y la sesión (-03:00) lo toma como
        // 11:00 UTC. La ida y la vuelta por PHP son exactas; el instante dentro de MySQL
        // queda corrido una hora, por eso no se compara con la hora de MySQL (CLAUDE.md §3).
        $instant = Carbon::parse('2023-07-01T12:00:00Z');
        $patient = $this->patientOf($this->clientIn($this->createClinic()), ['deceased_at' => '2023-07-01T12:00:00Z']);

        $row = DB::selectOne(
            'SELECT deceased_at, UNIX_TIMESTAMP(deceased_at) AS epoch FROM patients WHERE id = ?',
            [$patient->id],
        );

        $this->assertSame('2023-07-01 08:00:00', $row->deceased_at);
        $this->assertSame($instant->getTimestamp() - 3600, (int) $row->epoch);

        $read = $patient->fresh()->deceased_at;
        $this->assertTrue($read->equalTo($instant));
        $this->assertSame('2023-07-01T12:00:00.000000Z', $read->toISOString());
    }

    public function test_use_current_gives_asuncion_time(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $warehouseId = DB::table('warehouses')->where('branch_id', $branch->id)->value('id');

        $productId = DB::table('products')->insertGetId([
            'clinic_id' => $clinic->id,
            'code' => 'PRO-000001',
            'name' => 'Producto de prueba',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Sin created_at: lo pone MySQL (useCurrent()), con el reloj de la sesión.
        $movementId = DB::table('inventory_movements')->insertGetId([
            'clinic_id' => $clinic->id,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'type' => 'adjustment',
            'quantity' => '1.000',
            'unit_cost' => '0.0000',
            'total_cost' => '0.00',
            'balance_after' => '1.000',
            'average_cost_after' => '0.0000',
            'created_by' => $this->userIn($clinic)->id,
        ]);

        $createdAt = Carbon::parse(
            DB::table('inventory_movements')->where('id', $movementId)->value('created_at'),
            'America/Asuncion',
        );

        // El reloj de MySQL y el de PHP no son el mismo: unos segundos de tolerancia.
        $this->assertLessThanOrEqual(5, abs($createdAt->diffInSeconds(now('America/Asuncion'))));
    }
}
