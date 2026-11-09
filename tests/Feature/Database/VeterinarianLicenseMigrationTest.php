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
 * alguna pasa de 30 (no se corta). La bajada sin matrículas largas y la nueva subida,
 * en MySQL (en SQLite se omite).
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

    /**
     * Revisión de la 2.3: sin matrículas de más de 30, la bajada vuelve la columna a 30
     * sin cortar ninguna, y la subida la deja otra vez en 60. Solo en MySQL: es donde la
     * columna tiene largo (SQLite no lo aplica).
     *
     * El ALTER TABLE de MySQL confirma la transacción del test (RefreshDatabase): las
     * filas se insertan con DB::table, sin auditoría, y se borran al final; la subida va
     * en finally para que la base quede como la dejó la migración.
     */
    public function test_rolling_back_without_longer_license_numbers_narrows_the_column_and_keeps_them(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('El largo de la columna solo se aplica en MySQL.');
        }

        $migration = require base_path(self::MIGRATION);
        $license = str_repeat('7', 30);
        // Los datos de la ClinicFactory (RUC ficticio, R-013), sin el modelo: sin auditoría.
        $clinicId = DB::table('clinics')->insertGetId([
            ...\App\Models\Clinic::factory()->raw(),
            'status' => \App\Enums\ClinicStatus::ACTIVE->value,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $userId = DB::table('users')->insertGetId([
            'clinic_id' => $clinicId, 'first_name' => 'Ana', 'last_name' => 'Bajada', 'name' => 'Ana Bajada',
            'email' => 'ana.bajada@example.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $veterinarianId = DB::table('veterinarians')->insertGetId([
            'clinic_id' => $clinicId, 'user_id' => $userId, 'code' => 'VET-000001', 'license_number' => $license,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            $migration->down();

            $this->assertSame('varchar(30)', $this->licenseColumn()['type']);
            $this->assertSame($license, DB::table('veterinarians')->where('id', $veterinarianId)->value('license_number'));
        } finally {
            $migration->up();
            DB::table('veterinarians')->where('id', $veterinarianId)->delete();
            DB::table('users')->where('id', $userId)->delete();
            DB::table('clinics')->where('id', $clinicId)->delete();
        }

        $this->assertSame('varchar(60)', $this->licenseColumn()['type']);
    }

    /**
     * @return array<string, mixed>
     */
    private function licenseColumn(): array
    {
        return collect(Schema::getColumns('veterinarians'))->firstWhere('name', 'license_number');
    }
}
