<?php

namespace Tests\Feature\Patients;

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use App\Support\CurrentClinic;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithAuth;
use Tests\Concerns\InteractsWithClinicalRecords;
use Tests\TestCase;

/**
 * Baja de pacientes (CLAUDE.md §3, datos maestros, y §6.2; FASE 1, entrega 1.3).
 * La baja es solo del administrador (R-015): se prueba con un rol propio que
 * tiene patients.destroy. PATIENT_IN_USE solo se prueba aquí: la API todavía no
 * crea consultas, aplicaciones ni turnos.
 */
class PatientDeleteTest extends TestCase
{
    use InteractsWithAuth;
    use InteractsWithClinicalRecords;

    public function test_a_patient_without_use_is_deleted(): void
    {
        $clinic = $this->createClinic();
        $patient = $this->patientOf($this->clientIn($clinic));
        $this->actAsDeleter($clinic)->givePermissionTo('patients.show');

        $this->deleteJson("/api/patients/{$patient->id}")->assertOk()->assertJsonPath('data', null);

        $this->assertSoftDeleted('patients', ['id' => $patient->id]);
        $this->getJson("/api/patients/{$patient->id}")->assertNotFound();
    }

    public function test_a_patient_with_consultations_is_in_use(): void
    {
        $clinic = $this->createClinic();
        $client = $this->clientIn($clinic);
        $patient = $this->patientOf($client);
        $this->consultationFor($patient, $client);

        $this->assertInUse($clinic, $patient);
    }

    public function test_a_patient_with_applications_is_in_use(): void
    {
        $clinic = $this->createClinic();
        $patient = $this->patientOf($this->clientIn($clinic));
        $this->applicationFor($patient);

        $this->assertInUse($clinic, $patient);
    }

    public function test_a_patient_with_appointments_is_in_use(): void
    {
        $clinic = $this->createClinic();
        $patient = $this->patientOf($this->clientIn($clinic));
        $this->appointmentFor($patient);

        $this->assertInUse($clinic, $patient);
    }

    public function test_the_deleted_microchip_is_free_again(): void
    {
        $this->freezeTime();
        $clinic = $this->createClinic();
        $client = $this->clientIn($clinic);
        $patient = $this->patientOf($client, ['microchip' => 'CHIP777']);
        $this->actAsDeleter($clinic)->givePermissionTo('patients.store');

        $this->deleteJson("/api/patients/{$patient->id}")->assertOk();

        $this->assertSame("CHIP777//deleted_{$patient->id}_" . now()->timestamp, $this->trashedMicrochip($clinic, $patient));
        $this->postJson("/api/clients/{$client->id}/patients", $this->patientPayload(['microchip' => 'CHIP777']))->assertCreated();
    }

    /**
     * El id del registro en el sufijo: el mismo microchip se da de baja dos veces
     * en el mismo segundo sin chocar con el índice único.
     */
    public function test_the_same_microchip_can_be_deleted_twice_in_the_same_second(): void
    {
        $this->freezeTime();
        $clinic = $this->createClinic();
        $client = $this->clientIn($clinic);
        $this->actAsDeleter($clinic);

        $first = $this->patientOf($client, ['microchip' => 'A1']);
        $this->deleteJson("/api/patients/{$first->id}")->assertOk();

        $second = $this->patientOf($client, ['microchip' => 'A1']);
        $this->deleteJson("/api/patients/{$second->id}")->assertOk();

        $timestamp = now()->timestamp;
        $this->assertSame("A1//deleted_{$first->id}_{$timestamp}", $this->trashedMicrochip($clinic, $first));
        $this->assertSame("A1//deleted_{$second->id}_{$timestamp}", $this->trashedMicrochip($clinic, $second));
    }

    /**
     * El caso más largo: un chip de 30 y un id de 9 dígitos llenan los 60
     * caracteres de la columna sin cortar el sufijo (en MySQL, un valor más largo
     * falla).
     */
    public function test_the_longest_suffix_fits_without_being_cut(): void
    {
        $this->freezeTime();
        $clinic = $this->createClinic();
        $client = $this->clientIn($clinic);
        $chip = str_repeat('9', 30);
        $patient = CurrentClinic::run($clinic, fn () => $client->patients()->forceCreate([
            'id' => 999999999,
            'code' => 'TST-999999',
            'name' => 'Largo',
            'species_id' => $this->globalSpeciesId(),
            'microchip' => $chip,
        ]));
        $this->actAsDeleter($clinic);

        $this->deleteJson("/api/patients/{$patient->id}")->assertOk();

        $expected = "{$chip}//deleted_999999999_" . now()->timestamp;
        $this->assertSame(60, strlen($expected));
        $this->assertSame($expected, $this->trashedMicrochip($clinic, $patient));
    }

    public function test_the_receptionist_cannot_delete(): void
    {
        $clinic = $this->createClinic();
        $patient = $this->patientOf($this->clientIn($clinic));
        Sanctum::actingAs($this->userWithRole($clinic, 'recepcionista'));

        $this->assertErrorCode($this->deleteJson("/api/patients/{$patient->id}"), 403, 'FORBIDDEN');
        $this->assertNotSoftDeleted('patients', ['id' => $patient->id]);
    }

    private function assertInUse(Clinic $clinic, Patient $patient): void
    {
        $this->actAsDeleter($clinic);

        $response = $this->deleteJson("/api/patients/{$patient->id}");

        $this->assertErrorCode($response, 409, 'PATIENT_IN_USE');
        $response->assertJsonPath('message', 'El paciente tiene consultas, aplicaciones o turnos y no puede eliminarse. Si falleció, registrá la fecha de fallecimiento.');
        $this->assertNotSoftDeleted('patients', ['id' => $patient->id]);
    }

    private function actAsDeleter(Clinic $clinic): User
    {
        Role::findOrCreate('baja-pacientes', 'api')->syncPermissions(['patients.destroy']);
        $user = $this->userWithRole($clinic, 'baja-pacientes');
        Sanctum::actingAs($user);

        return $user;
    }

    private function trashedMicrochip(Clinic $clinic, Patient $patient): ?string
    {
        return CurrentClinic::run($clinic, fn () => Patient::onlyTrashed()->findOrFail($patient->id)->microchip);
    }
}
