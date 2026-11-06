<?php

namespace Tests\Feature\Localization;

use App\Support\CurrentClinic;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithClinics;
use Tests\TestCase;

/**
 * Los mensajes que quedaron en tú o usted del paquete base, ya en voseo (R-004;
 * FASE 1, entrega 1.1). El 403 genérico está en ErrorResponseTest.
 */
class VoseoMessagesTest extends TestCase
{
    use InteractsWithClinics;

    public function test_missing_permission_answers_in_voseo(): void
    {
        $this->actingIn($this->createClinic());

        $this->getJson('/api/clients')
            ->assertForbidden()
            ->assertJson([
                'message' => 'No tenés permisos para realizar esta acción.',
                'error_code' => 'FORBIDDEN',
            ]);
    }

    public function test_a_company_with_a_person_name_is_told_to_use_the_business_name(): void
    {
        $this->actingIn($this->createClinic(), ['clients.store']);

        $this->postJson('/api/clients', $this->clientPayload(['kind' => 'company', 'business_name' => 'Haras del Sur SA']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.first_name.0', 'Una empresa no lleva nombre: usá la razón social.')
            ->assertJsonPath('errors.last_name.0', 'Una empresa no lleva apellido: usá la razón social.');
    }

    public function test_the_kind_filter_of_the_client_list_uses_the_shared_message(): void
    {
        $this->actingIn($this->createClinic(), ['clients.index']);

        $this->getJson('/api/clients?kind=x')
            ->assertUnprocessable()
            ->assertJsonPath('errors.kind.0', 'El tipo de cliente debe ser person (persona) o company (empresa).');
    }

    public function test_a_patient_in_use_is_told_to_register_the_date_of_death(): void
    {
        $clinic = $this->createClinic();
        $patient = $this->patientOf($this->clientIn($clinic));
        $user = $this->actingIn($clinic, ['patients.destroy']);

        CurrentClinic::run($clinic, fn () => DB::table('patient_applications')->insert([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'type' => 'vaccine',
            'name' => 'Antirrábica',
            'applied_at' => now(),
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        $this->deleteJson("/api/patients/{$patient->id}")
            ->assertConflict()
            ->assertJson([
                'message' => 'El paciente tiene consultas, aplicaciones o turnos y no puede eliminarse. Si falleció, registrá la fecha de fallecimiento.',
                'error_code' => 'PATIENT_IN_USE',
            ]);
    }
}
