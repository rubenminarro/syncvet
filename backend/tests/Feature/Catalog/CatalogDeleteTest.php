<?php

namespace Tests\Feature\Catalog;

use App\Models\Species;
use App\Support\CurrentClinic;
use App\Support\DeletedSuffix;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

/**
 * Baja de especies y razas (FASE 1, entrega 1.4): solo si ningún paciente,
 * tampoco uno eliminado, las usa (409 SPECIES_IN_USE o BREED_IN_USE). La baja de
 * una especie se lleva sus razas. El nombre queda libre con el sufijo entero.
 * Las globales no se eliminan (403).
 */
class CatalogDeleteTest extends TestCase
{
    use InteractsWithCatalog;

    public function test_a_species_without_use_is_deleted_with_its_breeds_and_frees_the_names(): void
    {
        $this->actAsVet($this->createClinic());
        $ferret = $this->createSpecies('Hurón');
        $breed = $this->createBreed($ferret, 'Albino');

        $this->deleteJson("/api/species/{$ferret}")->assertOk()->assertJsonPath('data', null);

        $this->assertSoftDeleted('species', ['id' => $ferret]);
        $this->assertSoftDeleted('breeds', ['id' => $breed]);
        $this->assertStringStartsWith('Hurón' . DeletedSuffix::PREFIX . $ferret . '_', $this->speciesRow($ferret)->name);
        $this->assertStringStartsWith('Albino' . DeletedSuffix::PREFIX . $breed . '_', $this->breedRow($breed)->name);

        $again = $this->createSpecies('Hurón');
        $this->createBreed($again, 'Albino');
    }

    public function test_a_species_used_by_a_patient_is_in_use(): void
    {
        $clinic = $this->createClinic();
        $this->actAsVet($clinic);
        $ferret = $this->createSpecies('Hurón');
        $this->patientOf($this->clientIn($clinic), ['species_id' => $ferret]);

        $this->assertErrorCode($this->deleteJson("/api/species/{$ferret}"), 409, 'SPECIES_IN_USE');
        $this->assertNull($this->speciesRow($ferret)->deleted_at);
    }

    public function test_a_species_used_by_a_deleted_patient_without_breed_is_in_use(): void
    {
        $clinic = $this->createClinic();
        $this->actAsVet($clinic);
        $ferret = $this->createSpecies('Hurón');
        $patient = $this->patientOf($this->clientIn($clinic), ['species_id' => $ferret]);
        CurrentClinic::run($clinic, fn () => $patient->delete());

        $this->assertErrorCode($this->deleteJson("/api/species/{$ferret}"), 409, 'SPECIES_IN_USE');
        $this->assertNull($this->speciesRow($ferret)->deleted_at);
    }

    public function test_a_species_and_breed_used_by_a_deleted_patient_are_in_use(): void
    {
        $clinic = $this->createClinic();
        $this->actAsVet($clinic);
        $ferret = $this->createSpecies('Hurón');
        $breed = $this->createBreed($ferret, 'Albino');
        $patient = $this->patientOf($this->clientIn($clinic), ['species_id' => $ferret, 'breed_id' => $breed]);
        CurrentClinic::run($clinic, fn () => $patient->delete());

        $this->assertErrorCode($this->deleteJson("/api/species/{$ferret}"), 409, 'SPECIES_IN_USE');
        $this->assertErrorCode($this->deleteJson("/api/species/{$ferret}/breeds/{$breed}"), 409, 'BREED_IN_USE');
        $this->assertNull($this->speciesRow($ferret)->deleted_at);
        $this->assertNull($this->breedRow($breed)->deleted_at);
    }

    /**
     * Una raza en uso deja a su especie en uso, aunque (por un dato viejo) el
     * paciente tenga otra especie: la baja de la especie se llevaría la raza.
     */
    public function test_a_species_with_a_breed_in_use_is_in_use(): void
    {
        $clinic = $this->createClinic();
        $this->actAsVet($clinic);
        $ferret = $this->createSpecies('Hurón');
        $breed = $this->createBreed($ferret, 'Albino');
        $this->patientOf($this->clientIn($clinic), ['species_id' => $this->globalSpeciesId(), 'breed_id' => $breed]);

        $this->assertErrorCode($this->deleteJson("/api/species/{$ferret}"), 409, 'SPECIES_IN_USE');
        $this->assertNull($this->breedRow($breed)->deleted_at);
    }

    public function test_an_inactive_species_in_use_can_be_inactivated_but_not_deleted(): void
    {
        $clinic = $this->createClinic();
        $this->actAsVet($clinic);
        $ferret = $this->createSpecies('Hurón');
        $this->patientOf($this->clientIn($clinic), ['species_id' => $ferret]);

        $this->patchJson("/api/species/{$ferret}", ['is_active' => false])->assertOk();
        $this->assertErrorCode($this->deleteJson("/api/species/{$ferret}"), 409, 'SPECIES_IN_USE');
    }

    public function test_a_breed_without_use_is_deleted_and_frees_its_name(): void
    {
        $this->actAsVet($this->createClinic());
        $canine = $this->globalSpeciesId();
        $breed = $this->createBreed($canine, 'Fila');

        $this->deleteJson("/api/species/{$canine}/breeds/{$breed}")->assertOk()->assertJsonPath('data', null);

        $this->assertSoftDeleted('breeds', ['id' => $breed]);
        $this->createBreed($canine, 'Fila');
        $this->assertNull($this->speciesRow($canine)->deleted_at);
    }

    public function test_a_breed_used_by_a_patient_is_in_use(): void
    {
        $clinic = $this->createClinic();
        $this->actAsVet($clinic);
        $canine = $this->globalSpeciesId();
        $breed = $this->createBreed($canine, 'Fila');
        $this->patientOf($this->clientIn($clinic), ['breed_id' => $breed]);

        $this->assertErrorCode($this->deleteJson("/api/species/{$canine}/breeds/{$breed}"), 409, 'BREED_IN_USE');
        $this->assertNull($this->breedRow($breed)->deleted_at);
    }

    public function test_global_species_and_breeds_cannot_be_deleted(): void
    {
        $this->actAsVet($this->createClinic());
        $reptile = $this->globalSpeciesId('Reptil');
        $canine = $this->globalSpeciesId();
        $labrador = $this->globalBreedId('Labrador Retriever');

        $this->assertErrorCode($this->deleteJson("/api/species/{$reptile}"), 403, 'CATALOG_GLOBAL_NOT_EDITABLE');
        $this->assertErrorCode($this->deleteJson("/api/species/{$canine}/breeds/{$labrador}"), 403, 'CATALOG_GLOBAL_NOT_EDITABLE');

        $this->assertNull($this->speciesRow($reptile)->deleted_at);
        $this->assertSame('Reptil', $this->speciesRow($reptile)->name);
        $this->assertNull($this->breedRow($labrador)->deleted_at);
    }

    /**
     * Los nombres más largos (60 y 80) se eliminan con el sufijo entero, sin cortar.
     */
    public function test_the_longest_names_are_deleted_with_the_whole_suffix(): void
    {
        $this->actAsVet($this->createClinic());
        $species = $this->createSpecies(str_repeat('e', 60));
        $breed = $this->createBreed($species, str_repeat('r', 80));

        $this->deleteJson("/api/species/{$species}")->assertOk();

        $this->assertMatchesRegularExpression('#^e{60}//deleted_' . $species . '_\d{10}$#', $this->speciesRow($species)->name);
        $this->assertMatchesRegularExpression('#^r{80}//deleted_' . $breed . '_\d{10}$#', $this->breedRow($breed)->name);
    }

    /**
     * El caso más largo, con un id de 9 dígitos, llena las columnas (90 y 110). El
     * id es un dato del sufijo, no un registro guardado: forzarlo correría el
     * autoincremento de MySQL para el resto de la suite (R-021). En MySQL, un valor
     * más largo que la columna falla al guardar.
     */
    public function test_the_longest_suffix_fits_the_columns(): void
    {
        $this->freezeTime();
        $clinic = $this->createClinic();
        $this->actAsVet($clinic);
        $species = $this->createSpecies('Hurón');
        $breed = $this->createBreed($species, 'Albino');
        $suffix = DeletedSuffix::for((new Species)->forceFill(['id' => 999999999]));

        $longestSpecies = str_repeat('e', 60) . $suffix;
        $longestBreed = str_repeat('r', 80) . $suffix;
        $this->assertSame(90, strlen($longestSpecies));
        $this->assertSame(110, strlen($longestBreed));

        DB::table('species')->where('id', $species)->update(['name' => $longestSpecies]);
        DB::table('breeds')->where('id', $breed)->update(['name' => $longestBreed]);
        $this->assertSame($longestSpecies, $this->speciesRow($species)->name);
        $this->assertSame($longestBreed, $this->breedRow($breed)->name);
    }

    public function test_deletions_are_audited(): void
    {
        $this->actAsVet($this->createClinic());
        $ferret = $this->createSpecies('Hurón');
        $breed = $this->createBreed($ferret, 'Albino');

        $this->deleteJson("/api/species/{$ferret}")->assertOk();

        $this->assertTrue(DB::table('audit_logs')->where('auditable_type', 'species')->where('auditable_id', $ferret)->where('event', 'deleted')->exists());
        $this->assertTrue(DB::table('audit_logs')->where('auditable_type', 'breed')->where('auditable_id', $breed)->where('event', 'deleted')->exists());
    }
}
