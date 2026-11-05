<?php

namespace Tests\Feature\Tenancy;

use App\Exceptions\CatalogException;
use App\Models\Breed;
use App\Models\Species;
use App\Support\CurrentClinic;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithClinics;
use Tests\TestCase;

/**
 * Especies y razas (SharedCatalog): cada clínica ve las globales y las suyas, no
 * las de otra; las globales no se editan ni se eliminan.
 */
class SharedCatalogTest extends TestCase
{
    use InteractsWithClinics;

    public function test_clinic_sees_global_species_and_its_own_but_not_others(): void
    {
        $own = $this->createClinic();
        $other = $this->createClinic();
        CurrentClinic::run($own, fn () => Species::create(['name' => 'Hurón propio']));
        CurrentClinic::run($other, fn () => Species::create(['name' => 'Hurón ajeno']));

        $this->actingIn($own, ['species.index']);

        $names = collect($this->getJson('/api/species')->assertOk()->json('data'))->pluck('name');

        $this->assertContains('Canino', $names);
        $this->assertContains('Hurón propio', $names);
        $this->assertNotContains('Hurón ajeno', $names);
    }

    public function test_clinic_sees_global_breeds_and_its_own_but_not_others(): void
    {
        $own = $this->createClinic();
        $other = $this->createClinic();
        $canine = $this->globalSpeciesId();
        CurrentClinic::run($own, fn () => Breed::create(['species_id' => $canine, 'name' => 'Raza propia']));
        CurrentClinic::run($other, fn () => Breed::create(['species_id' => $canine, 'name' => 'Raza ajena']));

        $names = CurrentClinic::run($own, fn () => Breed::where('species_id', $canine)->pluck('name'));

        $this->assertContains('Labrador Retriever', $names);
        $this->assertContains('Raza propia', $names);
        $this->assertNotContains('Raza ajena', $names);
    }

    public function test_new_species_belongs_to_the_clinic_in_context(): void
    {
        $own = $this->createClinic();

        $species = CurrentClinic::run($own, fn () => Species::create(['name' => 'Hurón']));

        $this->assertSame($own->id, DB::table('species')->where('id', $species->id)->value('clinic_id'));
    }

    public function test_global_species_cannot_be_edited(): void
    {
        $own = $this->createClinic();

        $this->assertGlobalNotEditable(fn () => CurrentClinic::run($own, fn () => Species::where('name', 'Canino')->first()->update(['name' => 'Perro'])));
        $this->assertSame(1, DB::table('species')->whereNull('clinic_id')->where('name', 'Canino')->count());
    }

    public function test_global_species_and_breeds_cannot_be_deleted(): void
    {
        $own = $this->createClinic();

        $this->assertGlobalNotEditable(fn () => CurrentClinic::run($own, fn () => Species::where('name', 'Reptil')->first()->delete()));
        $this->assertGlobalNotEditable(fn () => CurrentClinic::run($own, fn () => Breed::where('name', 'Persa')->first()->delete()));
        $this->assertSame(1, DB::table('species')->where('name', 'Reptil')->count());
        $this->assertSame(1, DB::table('breeds')->where('name', 'Persa')->count());
    }

    public function test_own_species_can_be_edited(): void
    {
        $own = $this->createClinic();

        CurrentClinic::run($own, function () {
            $species = Species::create(['name' => 'Hurón']);
            $species->update(['name' => 'Hurón doméstico']);

            $this->assertSame('Hurón doméstico', $species->fresh()->name);
        });
    }

    public function test_patient_cannot_use_species_or_breed_of_another_clinic(): void
    {
        $own = $this->createClinic();
        $other = $this->createClinic();
        [$foreignSpecies, $foreignBreed] = CurrentClinic::run($other, fn () => [
            $species = Species::create(['name' => 'Hurón ajeno']),
            Breed::create(['species_id' => $species->id, 'name' => 'Raza ajena']),
        ]);
        $client = $this->clientIn($own);
        $this->actingIn($own, ['patients.store']);

        $response = $this->postJson("/api/clients/{$client->id}/patients", [
            'name' => 'Toby',
            'species_id' => $foreignSpecies->id,
            'breed_id' => $foreignBreed->id,
        ]);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonValidationErrors(['species_id', 'breed_id']);
    }

    public function test_patient_update_cannot_use_species_or_breed_of_another_clinic(): void
    {
        $own = $this->createClinic();
        [$foreignSpecies, $foreignBreed] = CurrentClinic::run($this->createClinic(), fn () => [
            $species = Species::create(['name' => 'Hurón ajeno']),
            Breed::create(['species_id' => $species->id, 'name' => 'Raza ajena']),
        ]);
        $patient = $this->patientOf($this->clientIn($own));
        $this->actingIn($own, ['patients.update']);

        $response = $this->patchJson("/api/patients/{$patient->id}", [
            'species_id' => $foreignSpecies->id,
            'breed_id' => $foreignBreed->id,
        ]);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonValidationErrors(['species_id', 'breed_id']);
    }

    public function test_patient_can_use_global_species_and_own_species(): void
    {
        $own = $this->createClinic();
        [$ownSpecies, $ownBreed] = CurrentClinic::run($own, fn () => [
            $species = Species::create(['name' => 'Hurón propio']),
            Breed::create(['species_id' => $species->id, 'name' => 'Raza propia']),
        ]);
        $client = $this->clientIn($own);
        $this->actingIn($own, ['patients.store']);

        $this->postJson("/api/clients/{$client->id}/patients", ['name' => 'Toby', 'species_id' => $this->globalSpeciesId()])
            ->assertCreated();
        $this->postJson("/api/clients/{$client->id}/patients", ['name' => 'Rocco', 'species_id' => $ownSpecies->id, 'breed_id' => $ownBreed->id])
            ->assertCreated();
    }

    private function assertGlobalNotEditable(\Closure $action): void
    {
        try {
            $action();
            $this->fail('Se modificó un registro global del catálogo.');
        } catch (CatalogException $e) {
            $this->assertSame('CATALOG_GLOBAL_NOT_EDITABLE', $e->getErrorCode());
            $this->assertSame(403, $e->getStatus());
        }
    }
}
