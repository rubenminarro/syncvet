<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Migración adjust_consultations_for_phase_4 (FASE 4, entrega 4.1; C3, C7 y pregunta 6 del
 * diseño, R-099): plan pasó a treatment, se sumó instructions, closed_at y cancelled_at son
 * datetime (en MySQL; deuda 17) y el índice único de appointment_id tiene un nombre propio
 * (ConsultationService::UNIQUE_APPOINTMENT). La bajada y la nueva subida, en MySQL.
 */
class ConsultationsPhase4MigrationTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_11_21_000000_adjust_consultations_for_phase_4.php';

    public function test_the_columns(): void
    {
        $columns = collect(Schema::getColumns('consultations'))->keyBy('name');

        $this->assertFalse($columns->has('plan'));
        $this->assertTrue($columns['treatment']['nullable']);
        $this->assertTrue($columns['instructions']['nullable']);

        if (DB::getDriverName() === 'mysql') {
            $this->assertSame('text', $columns['treatment']['type']);
            $this->assertSame('text', $columns['instructions']['type']);
            $this->assertSame('datetime', $columns['closed_at']['type']);
            $this->assertSame('datetime', $columns['cancelled_at']['type']);
        }
    }

    public function test_the_unique_index_of_the_appointment_has_its_own_name(): void
    {
        $indexes = collect(Schema::getIndexes('consultations'))->keyBy('name');

        $this->assertTrue($indexes->has('consultations_appointment_unique'));
        $this->assertTrue($indexes['consultations_appointment_unique']['unique']);
        $this->assertSame(['appointment_id'], $indexes['consultations_appointment_unique']['columns']);
        $this->assertFalse($indexes->has('consultations_appointment_id_unique'));
    }

    /**
     * El ALTER TABLE de MySQL confirma la transacción del test (RefreshDatabase): la tabla está
     * vacía y la subida va en finally, para que la base quede como la dejó la migración.
     */
    public function test_rolling_back_and_up_again(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('La bajada y la subida, en MySQL (los tipos de columna).');
        }

        $migration = require base_path(self::MIGRATION);

        try {
            $migration->down();

            $columns = collect(Schema::getColumns('consultations'))->keyBy('name');
            $this->assertTrue($columns->has('plan'));
            $this->assertFalse($columns->has('instructions'));
            $this->assertSame('timestamp', $columns['closed_at']['type']);
            $this->assertTrue(collect(Schema::getIndexes('consultations'))->contains('name', 'consultations_appointment_id_unique'));
        } finally {
            $migration->up();
        }

        $this->test_the_columns();
        $this->test_the_unique_index_of_the_appointment_has_its_own_name();
    }
}
