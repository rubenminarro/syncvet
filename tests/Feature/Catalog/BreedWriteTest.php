<?php

namespace Tests\Feature\Catalog;

use App\Services\BreedService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

/**
 * Alta y edición de razas en species/{species}/breeds (FASE 1, entrega 1.4):
 * únicas dentro de su especie sin distinguir mayúsculas ni acentos; una raza
 * propia en una especie global; la especie no se cambia; las globales no se
 * editan.
 */
class BreedWriteTest extends TestCase
{
    use InteractsWithCatalog;

    public function test_an_own_breed_in_a_global_species(): void
    {
        $clinic = $this->createClinic();
        $this->actAsVet($clinic);
        $canine = $this->globalSpeciesId();

        $response = $this->postJson("/api/species/{$canine}/breeds", ['name' => 'Fila Paraguayo'])->assertCreated();

        $response->assertJsonPath('data.species_id', $canine)
            ->assertJsonPath('data.name', 'Fila Paraguayo')
            ->assertJsonPath('data.is_global', false)
            ->assertJsonPath('data.is_active', true);
        $this->assertSame($clinic->id, $this->breedRow($response->json('data.id'))->clinic_id);
    }

    public function test_the_species_comes_from_the_url(): void
    {
        $this->actAsVet($this->createClinic());
        $canine = $this->globalSpeciesId();

        $id = $this->postJson("/api/species/{$canine}/breeds", ['name' => 'Fila Paraguayo', 'species_id' => $this->globalSpeciesId('Felino')])
            ->assertCreated()->json('data.id');

        $this->assertSame($canine, $this->breedRow($id)->species_id);
    }

    public function test_the_name_is_unique_within_its_species(): void
    {
        $this->actAsVet($this->createClinic());
        $canine = $this->globalSpeciesId();
        $feline = $this->globalSpeciesId('Felino');

        // Global de la misma especie, con otras mayúsculas y sin acento.
        foreach (['labrador retriever', 'PASTOR ALEMAN', ' Caniche '] as $name) {
            $response = $this->postJson("/api/species/{$canine}/breeds", ['name' => $name]);
            $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
            $response->assertJsonPath('errors.name.0', BreedService::NAME_TAKEN);
        }

        // "Siamés" es de Felino: en Canino está libre.
        $this->postJson("/api/species/{$canine}/breeds", ['name' => 'Siames'])->assertCreated();
        $this->assertErrorCode($this->postJson("/api/species/{$feline}/breeds", ['name' => 'siames']), 422, 'VALIDATION_ERROR');

        // Propia repetida.
        $this->createBreed($feline, 'Ragdoll');
        $this->assertErrorCode($this->postJson("/api/species/{$feline}/breeds", ['name' => 'RAGDOLL']), 422, 'VALIDATION_ERROR');
    }

    public function test_an_own_breed_in_an_own_species(): void
    {
        $this->actAsVet($this->createClinic());
        $ferret = $this->createSpecies('Hurón');

        $this->postJson("/api/species/{$ferret}/breeds", ['name' => 'Mestizo'])->assertCreated()->assertJsonPath('data.species_id', $ferret);
    }

    public function test_name_rules(): void
    {
        $this->actAsVet($this->createClinic());
        $canine = $this->globalSpeciesId();

        $this->postJson("/api/species/{$canine}/breeds", [])->assertUnprocessable()->assertJsonPath('errors.name.0', 'El nombre de la raza es obligatorio.');
        $this->postJson("/api/species/{$canine}/breeds", ['name' => str_repeat('a', 81)])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson("/api/species/{$canine}/breeds", ['name' => str_repeat('a', 80)])->assertCreated();
    }

    public function test_the_vet_renames_and_inactivates_an_own_breed(): void
    {
        $this->actAsVet($this->createClinic());
        $canine = $this->globalSpeciesId();
        $id = $this->createBreed($canine, 'Fila');

        $this->patchJson("/api/species/{$canine}/breeds/{$id}", ['name' => 'Fila Paraguayo'])->assertOk()->assertJsonPath('data.name', 'Fila Paraguayo');
        $this->putJson("/api/species/{$canine}/breeds/{$id}", ['is_active' => false])->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.name', 'Fila Paraguayo');

        $response = $this->patchJson("/api/species/{$canine}/breeds/{$id}", ['name' => 'Beagle']);
        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonPath('errors.name.0', BreedService::NAME_TAKEN);
        $this->patchJson("/api/species/{$canine}/breeds/{$id}", ['name' => 'FILA PARAGUAYO'])->assertOk();
    }

    public function test_the_species_of_a_breed_cannot_change(): void
    {
        $this->actAsVet($this->createClinic());
        $canine = $this->globalSpeciesId();
        $id = $this->createBreed($canine, 'Fila');

        $response = $this->patchJson("/api/species/{$canine}/breeds/{$id}", ['species_id' => $this->globalSpeciesId('Felino')]);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonValidationErrors('species_id');
        $this->assertSame($canine, $this->breedRow($id)->species_id);
    }

    public function test_a_global_breed_cannot_be_edited(): void
    {
        $this->actAsVet($this->createClinic());
        $canine = $this->globalSpeciesId();
        $labrador = $this->globalBreedId('Labrador Retriever');

        $this->assertErrorCode($this->patchJson("/api/species/{$canine}/breeds/{$labrador}", ['is_active' => false]), 403, 'CATALOG_GLOBAL_NOT_EDITABLE');
        $this->assertErrorCode($this->patchJson("/api/species/{$canine}/breeds/{$labrador}", ['name' => 'Beagle']), 403, 'CATALOG_GLOBAL_NOT_EDITABLE');

        $this->assertTrue((bool) $this->breedRow($labrador)->is_active);
    }

    public function test_a_breed_of_another_clinic_answers_404(): void
    {
        $this->actAsVet($this->createClinic());
        $canine = $this->globalSpeciesId();
        $foreign = $this->createBreed($canine, 'Fila');

        $this->actAsVet($this->createClinic());

        $this->assertErrorCode($this->patchJson("/api/species/{$canine}/breeds/{$foreign}", ['name' => 'Otra']), 404, 'RESOURCE_NOT_FOUND');
        $this->assertErrorCode($this->deleteJson("/api/species/{$canine}/breeds/{$foreign}"), 404, 'RESOURCE_NOT_FOUND');
        // Y en el listado de la especie global no aparece.
        $this->assertNotContains('Fila', collect($this->getJson("/api/species/{$canine}/breeds")->json('data'))->pluck('name'));
    }

    public function test_the_receptionist_cannot_write(): void
    {
        $clinic = $this->createClinic();
        $this->actAsVet($clinic);
        $canine = $this->globalSpeciesId();
        $id = $this->createBreed($canine, 'Fila');
        Sanctum::actingAs($this->userWithRole($clinic, 'recepcionista'));

        $this->getJson("/api/species/{$canine}/breeds")->assertOk();
        $this->assertErrorCode($this->postJson("/api/species/{$canine}/breeds", ['name' => 'Otra']), 403, 'FORBIDDEN');
        $this->assertErrorCode($this->patchJson("/api/species/{$canine}/breeds/{$id}", ['name' => 'Otra']), 403, 'FORBIDDEN');
        $this->assertErrorCode($this->deleteJson("/api/species/{$canine}/breeds/{$id}"), 403, 'FORBIDDEN');
        $this->assertSame('Fila', DB::table('breeds')->where('id', $id)->value('name'));
    }
}
