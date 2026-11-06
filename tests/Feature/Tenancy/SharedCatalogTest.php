<?php

namespace Tests\Feature\Tenancy;

use App\Exceptions\CatalogException;
use App\Exceptions\ClinicContextMissingException;
use App\Models\Breed;
use App\Models\Species;
use App\Support\CurrentClinic;
use Database\Seeders\SpeciesSeeder;
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

    /**
     * Un update() masivo no dispara los eventos del modelo: SharedCatalogBuilder lo
     * limita a los registros de la clínica. Ni los globales ni los de otra cambian.
     */
    public function test_bulk_update_only_touches_own_records(): void
    {
        $own = $this->createClinic();
        $other = $this->createClinic();
        $canine = $this->globalSpeciesId();
        $foreign = CurrentClinic::run($other, fn () => Breed::create(['species_id' => $canine, 'name' => 'Raza ajena']));
        $mine = CurrentClinic::run($own, fn () => Breed::create(['species_id' => $canine, 'name' => 'Raza propia']));

        CurrentClinic::run($own, function () use ($canine) {
            Species::where('name', 'Canino')->update(['is_active' => false]);
            Breed::where('species_id', $canine)->update(['is_active' => false]);
        });

        $this->assertTrue((bool) DB::table('species')->where('id', $canine)->value('is_active'), 'Se desactivó la especie global.');
        $this->assertSame(0, DB::table('breeds')->whereNull('clinic_id')->where('is_active', false)->count(), 'Se desactivaron razas globales.');
        $this->assertTrue((bool) DB::table('breeds')->where('id', $foreign->id)->value('is_active'), 'Se desactivó una raza de otra clínica.');
        $this->assertFalse((bool) DB::table('breeds')->where('id', $mine->id)->value('is_active'), 'No se desactivó la raza propia.');
    }

    public function test_bulk_delete_only_touches_own_records(): void
    {
        $own = $this->createClinic();
        $canine = $this->globalSpeciesId();
        $globalBreeds = DB::table('breeds')->whereNull('clinic_id')->count();

        CurrentClinic::run($own, function () use ($canine) {
            Species::create(['name' => 'Hurón']);
            Breed::create(['species_id' => $canine, 'name' => 'Raza propia']);

            Species::whereIn('name', ['Reptil', 'Hurón'])->delete();
            Breed::where('species_id', $canine)->delete();
            Breed::query()->forceDelete();
        });

        // Con SoftDeletes, la baja masiva es un update de deleted_at: también limitado a los propios.
        $this->assertTrue(DB::table('species')->whereNull('clinic_id')->where('name', 'Reptil')->whereNull('deleted_at')->exists(), 'Se eliminó la especie global.');
        $this->assertSame($globalBreeds, DB::table('breeds')->whereNull('clinic_id')->whereNull('deleted_at')->count(), 'Se eliminaron razas globales.');
        $this->assertSame(0, DB::table('species')->where('clinic_id', $own->id)->whereNull('deleted_at')->count(), 'No se eliminó la especie propia.');
        $this->assertSame(0, DB::table('breeds')->where('clinic_id', $own->id)->count(), 'No se eliminó la raza propia.');
    }

    public function test_bulk_update_cannot_change_clinic_id(): void
    {
        $own = $this->createClinic();
        CurrentClinic::run($own, fn () => Species::create(['name' => 'Hurón']));

        try {
            CurrentClinic::run($own, fn () => Species::where('name', 'Hurón')->update(['clinic_id' => null]));
            $this->fail('Una actualización masiva pasó una especie propia a global.');
        } catch (ClinicContextMissingException) {
            $this->assertSame($own->id, DB::table('species')->where('name', 'Hurón')->value('clinic_id'));
        }
    }

    public function test_own_species_cannot_become_global(): void
    {
        $own = $this->createClinic();
        $species = CurrentClinic::run($own, fn () => Species::create(['name' => 'Hurón']));

        try {
            CurrentClinic::run($own, fn () => $species->forceFill(['clinic_id' => null])->save());
            $this->fail('Una especie propia pasó a global.');
        } catch (ClinicContextMissingException) {
            $this->assertSame($own->id, DB::table('species')->where('id', $species->id)->value('clinic_id'));
        }
    }

    public function test_own_breed_cannot_move_to_another_clinic(): void
    {
        $own = $this->createClinic();
        $other = $this->createClinic();
        $breed = CurrentClinic::run($own, fn () => Breed::create(['species_id' => $this->globalSpeciesId(), 'name' => 'Raza propia']));

        $this->expectException(ClinicContextMissingException::class);

        CurrentClinic::run($own, fn () => $breed->forceFill(['clinic_id' => $other->id])->save());
    }

    /**
     * SpeciesSeeder busca y crea solo globales: una especie o raza propia con el
     * mismo nombre no reemplaza a la global que falta.
     */
    public function test_seeder_only_touches_global_records(): void
    {
        $own = $this->createClinic();
        $canine = $this->globalSpeciesId();
        DB::table('breeds')->whereNull('clinic_id')->where('name', 'Labrador Retriever')->delete();
        DB::table('species')->whereNull('clinic_id')->where('name', 'Otro')->delete();
        [$ownSpecies, $ownBreed] = CurrentClinic::run($own, fn () => [
            Species::create(['name' => 'Otro']),
            Breed::create(['species_id' => $canine, 'name' => 'Labrador Retriever']),
        ]);

        $this->seed(SpeciesSeeder::class);

        $this->assertTrue(DB::table('species')->whereNull('clinic_id')->where('name', 'Otro')->exists(), 'No se recreó la especie global.');
        $this->assertTrue(
            DB::table('breeds')->whereNull('clinic_id')->where('species_id', $canine)->where('name', 'Labrador Retriever')->exists(),
            'No se recreó la raza global.'
        );
        $this->assertSame($own->id, DB::table('species')->where('id', $ownSpecies->id)->value('clinic_id'));
        $this->assertSame($own->id, DB::table('breeds')->where('id', $ownBreed->id)->value('clinic_id'));
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
