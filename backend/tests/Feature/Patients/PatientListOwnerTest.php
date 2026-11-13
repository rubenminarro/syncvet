<?php

namespace Tests\Feature\Patients;

use App\Enums\ClientKind;
use App\Models\Client;
use App\Models\Clinic;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * El dueño en el listado de pacientes (entrega de soporte del frontend front-01, R-064):
 * GET patients trae client: { id, display_name }, sin N+1. El listado de un cliente, el
 * detalle del cliente y el detalle del paciente no cambian.
 */
class PatientListOwnerTest extends TestCase
{
    use InteractsWithAuth;

    private Clinic $clinic;

    private Client $marta;

    private Client $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = $this->createClinic();
        Sanctum::actingAs($this->userWithRole($this->clinic, 'recepcionista'));

        $this->marta = $this->clientIn($this->clinic, ['first_name' => 'Marta', 'last_name' => 'Benítez', 'document_number' => '99400201']);
        $this->empresa = $this->clientIn($this->clinic, [
            'kind' => ClientKind::COMPANY, 'first_name' => null, 'last_name' => null,
            'business_name' => 'Granja Los Robles', 'document_number' => '99400202',
        ]);
    }

    public function test_the_list_brings_the_owner_of_a_person_and_of_a_company(): void
    {
        $this->patientOf($this->marta, ['name' => 'Firulais']);
        $this->patientOf($this->empresa, ['name' => 'Manchas']);

        $response = $this->getJson('/api/patients')->assertOk()->assertJsonCount(2, 'data');

        // Por nombre: Firulais, Manchas. Exactamente id y display_name.
        $this->assertSame(['id' => $this->marta->id, 'display_name' => 'Marta Benítez'], $response->json('data.0.client'));
        $this->assertSame(['id' => $this->empresa->id, 'display_name' => 'Granja Los Robles'], $response->json('data.1.client'));
    }

    public function test_the_owner_goes_last_and_the_other_keys_do_not_change(): void
    {
        $this->patientOf($this->marta, ['name' => 'Firulais']);

        $this->assertSame([
            'id', 'code', 'name', 'species', 'breed', 'sex', 'is_neutered', 'birth_date', 'birth_date_estimated',
            'age', 'weight_kg', 'microchip', 'is_deceased', 'has_photo', 'client',
        ], array_keys($this->getJson('/api/patients')->assertOk()->json('data.0')));
    }

    /**
     * Sin N+1: la misma cantidad de consultas con un paciente que con seis, cada uno con
     * su dueño, su especie y su raza.
     */
    public function test_the_owner_is_loaded_without_n_plus_one(): void
    {
        $this->patientOf($this->marta, ['name' => 'Paciente 0', 'breed_id' => $this->globalBreedId('Labrador Retriever')]);

        // El primer pedido también carga la caché de permisos: no cuenta.
        $this->getJson('/api/patients')->assertOk();

        $one = $this->countQueries(fn () => $this->getJson('/api/patients')->assertOk()->assertJsonCount(1, 'data'));

        foreach (['99400211', '99400212', '99400213', '99400214', '99400215'] as $i => $document) {
            $owner = $this->clientIn($this->clinic, ['document_number' => $document]);
            $this->patientOf($owner, ['name' => 'Paciente ' . ($i + 1), 'breed_id' => $this->globalBreedId('Labrador Retriever')]);
        }

        $many = $this->countQueries(fn () => $this->getJson('/api/patients')->assertOk()->assertJsonCount(6, 'data')->assertJsonStructure(['data' => ['*' => ['client' => ['id', 'display_name']]]]));

        $this->assertSame($one, $many, 'La cantidad de consultas crece con los pacientes (N+1).');
    }

    public function test_an_owner_deleted_does_not_break_the_list(): void
    {
        $this->patientOf($this->marta, ['name' => 'Firulais']);
        // Fuera del Service (que no lo permite con pacientes): el dueño eliminado igual se muestra.
        DB::table('clients')->where('id', $this->marta->id)->update(['deleted_at' => now()]);

        $this->getJson('/api/patients')
            ->assertOk()
            ->assertJsonPath('data.0.client.id', $this->marta->id)
            ->assertJsonPath('data.0.client.display_name', 'Marta Benítez');
    }

    public function test_the_patients_of_a_client_and_the_client_detail_do_not_bring_the_owner(): void
    {
        $this->patientOf($this->marta, ['name' => 'Firulais']);

        $this->assertArrayNotHasKey('client', $this->getJson("/api/clients/{$this->marta->id}/patients")->assertOk()->json('data.0'));
        $this->assertArrayNotHasKey('client', $this->getJson("/api/clients/{$this->marta->id}")->assertOk()->json('data.patients.0'));
    }

    public function test_the_patient_detail_keeps_its_owner_and_the_order_of_its_keys(): void
    {
        $patient = $this->patientOf($this->marta, ['name' => 'Firulais']);

        $data = $this->getJson("/api/patients/{$patient->id}")->assertOk()->json('data');

        $this->assertSame([
            'id', 'code', 'name', 'species', 'breed', 'sex', 'is_neutered', 'birth_date', 'birth_date_estimated',
            'age', 'weight_kg', 'microchip', 'is_deceased', 'has_photo', 'client', 'color', 'allergies', 'notes',
            'deceased_at', 'created_at', 'updated_at',
        ], array_keys($data));
        $this->assertSame([
            'id' => $this->marta->id,
            'display_name' => 'Marta Benítez',
            'phone' => $this->marta->phone,
            'email' => $this->marta->email,
            'deleted' => false,
        ], $data['client']);
    }

    private function countQueries(callable $request): int
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        $request();

        return $count;
    }
}
