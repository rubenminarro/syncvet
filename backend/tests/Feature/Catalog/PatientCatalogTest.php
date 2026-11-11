<?php

namespace Tests\Feature\Catalog;

use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

/**
 * El catálogo visto desde los pacientes (FASE 1, entrega 1.4): una especie o
 * raza inactiva sigue en los pacientes que ya la usan, pero no se acepta en un
 * alta nueva; una eliminada no se acepta nunca.
 */
class PatientCatalogTest extends TestCase
{
    use InteractsWithCatalog;

    public function test_a_patient_keeps_showing_its_inactive_species_and_breed(): void
    {
        $clinic = $this->createClinic();
        $this->actAsVet($clinic);
        $ferret = $this->createSpecies('Hurón');
        $breed = $this->createBreed($ferret, 'Albino');
        $patient = $this->patientOf($this->clientIn($clinic), ['species_id' => $ferret, 'breed_id' => $breed]);

        $this->patchJson("/api/species/{$ferret}/breeds/{$breed}", ['is_active' => false])->assertOk();
        $this->patchJson("/api/species/{$ferret}", ['is_active' => false])->assertOk();

        $this->getJson("/api/patients/{$patient->id}")->assertOk()
            ->assertJsonPath('data.species.name', 'Hurón')
            ->assertJsonPath('data.breed.name', 'Albino');
        $this->assertContains($patient->id, collect($this->getJson('/api/patients')->assertOk()->json('data'))->pluck('id'));
    }

    public function test_a_new_patient_cannot_use_an_inactive_species_or_breed(): void
    {
        $clinic = $this->createClinic();
        $this->actAsVet($clinic);
        $client = $this->clientIn($clinic);
        $ferret = $this->createSpecies('Hurón', ['is_active' => false]);
        $canine = $this->globalSpeciesId();
        $breed = $this->createBreed($canine, 'Fila', ['is_active' => false]);

        $response = $this->postJson("/api/clients/{$client->id}/patients", $this->patientPayload(['species_id' => $ferret]));
        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonValidationErrors('species_id');

        $response = $this->postJson("/api/clients/{$client->id}/patients", $this->patientPayload(['breed_id' => $breed]));
        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonValidationErrors('breed_id');
    }

    public function test_a_patient_cannot_use_a_deleted_species_or_breed(): void
    {
        $clinic = $this->createClinic();
        $this->actAsVet($clinic);
        $client = $this->clientIn($clinic);
        $patient = $this->patientOf($client);
        $ferret = $this->createSpecies('Hurón');
        $canine = $this->globalSpeciesId();
        $breed = $this->createBreed($canine, 'Fila');
        $this->deleteJson("/api/species/{$ferret}")->assertOk();
        $this->deleteJson("/api/species/{$canine}/breeds/{$breed}")->assertOk();

        $this->postJson("/api/clients/{$client->id}/patients", $this->patientPayload(['species_id' => $ferret]))
            ->assertUnprocessable()->assertJsonValidationErrors('species_id');
        $this->postJson("/api/clients/{$client->id}/patients", $this->patientPayload(['breed_id' => $breed]))
            ->assertUnprocessable()->assertJsonValidationErrors('breed_id');
        $this->patchJson("/api/patients/{$patient->id}", ['species_id' => $ferret])
            ->assertUnprocessable()->assertJsonValidationErrors('species_id');
        $this->patchJson("/api/patients/{$patient->id}", ['breed_id' => $breed])
            ->assertUnprocessable()->assertJsonValidationErrors('breed_id');
    }

    public function test_a_patient_can_use_a_new_own_species_and_breed(): void
    {
        $clinic = $this->createClinic();
        $this->actAsVet($clinic);
        $client = $this->clientIn($clinic);
        $ferret = $this->createSpecies('Hurón');
        $breed = $this->createBreed($ferret, 'Albino');
        Sanctum::actingAs($this->userWithRole($clinic, 'recepcionista'));

        $this->postJson("/api/clients/{$client->id}/patients", $this->patientPayload(['species_id' => $ferret, 'breed_id' => $breed]))
            ->assertCreated()
            ->assertJsonPath('data.species.name', 'Hurón')
            ->assertJsonPath('data.breed.name', 'Albino');
    }
}
