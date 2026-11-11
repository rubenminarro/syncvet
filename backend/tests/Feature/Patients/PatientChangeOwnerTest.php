<?php

namespace Tests\Feature\Patients;

use App\Models\Clinic;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAuth;
use Tests\Concerns\InteractsWithClinicalRecords;
use Tests\TestCase;

/**
 * Cambio de dueño en POST patients/{patient}/change-owner (CLAUDE.md §6.2; FASE 1,
 * entrega 1.3), con el rol recepcionista.
 */
class PatientChangeOwnerTest extends TestCase
{
    use InteractsWithAuth;
    use InteractsWithClinicalRecords;

    private Clinic $clinic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = $this->createClinic();
        Sanctum::actingAs($this->userWithRole($this->clinic, 'recepcionista'));
    }

    public function test_the_owner_is_changed(): void
    {
        $patient = $this->patientOf($this->clientIn($this->clinic));
        $newOwner = $this->clientIn($this->clinic, ['first_name' => 'Marta', 'last_name' => 'Ríos']);

        $this->postJson("/api/patients/{$patient->id}/change-owner", ['client_id' => $newOwner->id])
            ->assertOk()
            ->assertJsonPath('data.client.id', $newOwner->id)
            ->assertJsonPath('data.client.display_name', 'Marta Ríos');

        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'client_id' => $newOwner->id]);
    }

    public function test_the_same_owner_is_rejected(): void
    {
        $patient = $this->patientOf($this->clientIn($this->clinic));

        $response = $this->postJson("/api/patients/{$patient->id}/change-owner", ['client_id' => $patient->client_id]);

        $this->assertErrorCode($response, 422, 'PATIENT_SAME_OWNER');
    }

    public function test_a_client_of_another_clinic_is_rejected(): void
    {
        $patient = $this->patientOf($this->clientIn($this->clinic));
        $foreign = $this->clientIn($this->createClinic());

        $this->postJson("/api/patients/{$patient->id}/change-owner", ['client_id' => $foreign->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.client_id.0', 'El cliente seleccionado no existe.');

        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'client_id' => $patient->client_id]);
    }

    public function test_a_deleted_client_is_rejected(): void
    {
        $patient = $this->patientOf($this->clientIn($this->clinic));
        $deleted = $this->clientIn($this->clinic);
        DB::table('clients')->where('id', $deleted->id)->update(['deleted_at' => now()]);

        $this->postJson("/api/patients/{$patient->id}/change-owner", ['client_id' => $deleted->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['client_id']);
    }

    public function test_the_new_owner_is_required(): void
    {
        $patient = $this->patientOf($this->clientIn($this->clinic));

        $this->postJson("/api/patients/{$patient->id}/change-owner", [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.client_id.0', 'El nuevo dueño es obligatorio.');
    }

    /**
     * Las consultas guardan el dueño de ese momento.
     */
    public function test_a_previous_consultation_keeps_its_owner(): void
    {
        $oldOwner = $this->clientIn($this->clinic);
        $patient = $this->patientOf($oldOwner);
        $consultationId = $this->consultationFor($patient, $oldOwner);
        $newOwner = $this->clientIn($this->clinic);

        $this->postJson("/api/patients/{$patient->id}/change-owner", ['client_id' => $newOwner->id])->assertOk();

        $this->assertSame($oldOwner->id, (int) DB::table('consultations')->where('id', $consultationId)->value('client_id'));
    }

    /**
     * Pregunta 17 del diseño: el cambio de dueño de un fallecido se permite
     * (corrige errores de carga).
     */
    public function test_the_owner_of_a_deceased_patient_can_be_changed(): void
    {
        $patient = $this->patientOf($this->clientIn($this->clinic), ['deceased_at' => now()->subDay()]);
        $newOwner = $this->clientIn($this->clinic);

        $this->postJson("/api/patients/{$patient->id}/change-owner", ['client_id' => $newOwner->id])
            ->assertOk()
            ->assertJsonPath('data.client.id', $newOwner->id);
    }

    public function test_the_veterinarian_cannot_change_the_owner(): void
    {
        $patient = $this->patientOf($this->clientIn($this->clinic));
        $newOwner = $this->clientIn($this->clinic);
        Sanctum::actingAs($this->userWithRole($this->clinic, 'veterinario'));

        $this->assertErrorCode(
            $this->postJson("/api/patients/{$patient->id}/change-owner", ['client_id' => $newOwner->id]),
            403,
            'FORBIDDEN'
        );
        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'client_id' => $patient->client_id]);
    }
}
