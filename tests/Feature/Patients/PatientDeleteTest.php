<?php

namespace Tests\Feature\Patients;

use App\Models\Patient;
use App\Support\CurrentClinic;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Baja de pacientes (CLAUDE.md §3, datos maestros). La entrega 1.3 suma las demás
 * reglas de §6.2. La baja es solo del administrador (R-015): se prueba con un rol
 * propio que tiene patients.destroy.
 */
class PatientDeleteTest extends TestCase
{
    use InteractsWithAuth;

    /**
     * El id del registro en el sufijo: el mismo microchip se da de baja dos veces
     * en el mismo segundo sin chocar con el índice único (entrega 1.2).
     */
    public function test_the_same_microchip_can_be_deleted_twice_in_the_same_second(): void
    {
        $this->freezeTime();
        $clinic = $this->createClinic();
        $client = $this->clientIn($clinic);
        Role::findOrCreate('baja-pacientes', 'api')->syncPermissions(['patients.destroy']);
        Sanctum::actingAs($this->userWithRole($clinic, 'baja-pacientes'));

        $first = $this->patientOf($client, ['microchip' => 'A1']);
        $this->deleteJson("/api/patients/{$first->id}")->assertOk();

        $second = $this->patientOf($client, ['microchip' => 'A1']);
        $this->deleteJson("/api/patients/{$second->id}")->assertOk();

        $timestamp = now()->timestamp;
        $microchips = CurrentClinic::run($clinic, fn () => Patient::onlyTrashed()->orderBy('id')->pluck('microchip')->all());

        // Hasta la 1.3 la columna tiene 30 caracteres: el corte deja el id entero
        // (2 + 10 del prefijo + el id) y como mucho recorta el timestamp.
        $this->assertSame([
            substr("A1//deleted_{$first->id}_{$timestamp}", 0, 30),
            substr("A1//deleted_{$second->id}_{$timestamp}", 0, 30),
        ], $microchips);
        $this->assertStringStartsWith("A1//deleted_{$second->id}_", $microchips[1]);
    }
}
