<?php

namespace Tests\Feature\Catalog;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

/**
 * scopeBindings() (deuda 8): species/{species}/breeds/{breed} es la primera ruta
 * con dos parámetros anidados. Una raza de otra especie de la misma clínica en
 * la URL responde 404 y no se toca. Sin scopeBindings() en routes/api.php, la
 * raza se resolvería sola por su id y este test fallaría.
 */
class NestedBindingTest extends TestCase
{
    use InteractsWithCatalog;

    public function test_a_breed_of_another_species_in_the_url_answers_404(): void
    {
        $this->actAsVet($this->createClinic());
        $ferret = $this->createSpecies('Hurón');
        $hedgehog = $this->createSpecies('Erizo');
        $breed = $this->createBreed($ferret, 'Albino');

        $this->assertErrorCode($this->patchJson("/api/species/{$hedgehog}/breeds/{$breed}", ['name' => 'Otro']), 404, 'RESOURCE_NOT_FOUND');
        $this->assertErrorCode($this->putJson("/api/species/{$hedgehog}/breeds/{$breed}", ['is_active' => false]), 404, 'RESOURCE_NOT_FOUND');
        $this->assertErrorCode($this->deleteJson("/api/species/{$hedgehog}/breeds/{$breed}"), 404, 'RESOURCE_NOT_FOUND');

        $row = DB::table('breeds')->where('id', $breed)->first();
        $this->assertSame('Albino', $row->name);
        $this->assertTrue((bool) $row->is_active);
        $this->assertNull($row->deleted_at);

        $this->patchJson("/api/species/{$ferret}/breeds/{$breed}", ['name' => 'Albino claro'])->assertOk();
    }

    public function test_a_global_breed_under_another_global_species_answers_404(): void
    {
        $this->actAsVet($this->createClinic());
        $feline = $this->globalSpeciesId('Felino');
        $labrador = $this->globalBreedId('Labrador Retriever');

        $this->assertErrorCode($this->deleteJson("/api/species/{$feline}/breeds/{$labrador}"), 404, 'RESOURCE_NOT_FOUND');
    }
}
