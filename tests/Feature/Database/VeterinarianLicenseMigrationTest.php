<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Concerns\InteractsWithVeterinarians;
use Tests\TestCase;

/**
 * Migración widen_veterinarians_license_number (FASE 2, entrega 2.3; pregunta 29): la
 * matrícula admite 60 caracteres, y volver atrás se detiene con un mensaje claro si
 * alguna pasa de 30 (no se corta). La subida, la bajada y la nueva subida en MySQL se
 * prueban a mano en cada entrega que toca la migración.
 */
class VeterinarianLicenseMigrationTest extends TestCase
{
    use InteractsWithVeterinarians;

    private const MIGRATION = 'database/migrations/2026_11_09_000000_widen_veterinarians_license_number.php';

    public function test_the_column_admits_60_characters(): void
    {
        $column = collect(Schema::getColumns('veterinarians'))->firstWhere('name', 'license_number');

        if (DB::getDriverName() === 'mysql') {
            $this->assertSame('varchar(60)', $column['type']);
        }

        $this->assertTrue($column['nullable']);
    }

    public function test_rolling_back_stops_with_a_clear_message_when_a_license_number_is_longer_than_30(): void
    {
        $clinic = $this->createClinic();
        $veterinarian = $this->veterinarianOf($this->veterinarianUser($clinic), ['license_number' => 'MV-ASUNCION-1001']);
        DB::table('veterinarians')->where('id', $veterinarian->id)->update(['license_number' => 'MV-ASUNCION-1001//deleted_' . $veterinarian->id . '_1762650000']);

        $error = null;

        try {
            (require base_path(self::MIGRATION))->down();
        } catch (RuntimeException $e) {
            $error = $e;
        }

        $this->assertNotNull($error, 'La bajada no se detuvo.');
        $this->assertStringContainsString('No se puede volver la matrícula de los veterinarios a 30 caracteres', $error->getMessage());
        $this->assertStringContainsString("ids {$veterinarian->id}", $error->getMessage());

        $this->assertStringStartsWith('MV-ASUNCION-1001//deleted_', DB::table('veterinarians')->where('id', $veterinarian->id)->value('license_number'));
    }
}
