<?php

namespace Tests\Feature\Patients;

use App\Models\Clinic;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Edición de pacientes (CLAUDE.md §6.2; FASE 1, entrega 1.3), con el rol
 * recepcionista.
 */
class PatientUpdateTest extends TestCase
{
    use InteractsWithAuth;

    private Clinic $clinic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = $this->createClinic();
        Sanctum::actingAs($this->userWithRole($this->clinic, 'recepcionista'));
    }

    public function test_the_owner_cannot_be_changed_by_patch(): void
    {
        $patient = $this->patientOf($this->clientIn($this->clinic));
        $other = $this->clientIn($this->clinic);

        $this->patchJson("/api/patients/{$patient->id}", ['client_id' => $other->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.client_id.0', 'El dueño se cambia con la acción "cambiar dueño".');

        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'client_id' => $patient->client_id]);
    }

    public function test_the_code_cannot_be_changed(): void
    {
        $patient = $this->patientOf($this->clientIn($this->clinic));

        $this->patchJson("/api/patients/{$patient->id}", ['code' => 'PAC-999999'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_changing_the_species_keeping_a_breed_of_the_previous_one_is_rejected(): void
    {
        $patient = $this->patientOf($this->clientIn($this->clinic), ['breed_id' => $this->globalBreedId('Beagle')]);

        $response = $this->patchJson("/api/patients/{$patient->id}", ['species_id' => $this->globalSpeciesId('Felino')]);

        $this->assertErrorCode($response, 422, 'PATIENT_BREED_SPECIES_MISMATCH');
        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'species_id' => $this->globalSpeciesId()]);
    }

    public function test_changing_the_species_and_the_breed_together_is_accepted(): void
    {
        $patient = $this->patientOf($this->clientIn($this->clinic), ['breed_id' => $this->globalBreedId('Beagle')]);

        $this->patchJson("/api/patients/{$patient->id}", [
            'species_id' => $this->globalSpeciesId('Felino'),
            'breed_id' => $this->globalBreedId('Siamés', 'Felino'),
        ])->assertOk()->assertJsonPath('data.breed.name', 'Siamés');
    }

    /**
     * El formulario reenvía la especie y la raza que el paciente ya tiene, aunque
     * se hayan inactivado después.
     */
    public function test_keeping_an_inactive_species_and_breed_it_already_has_is_accepted(): void
    {
        [$speciesId, $breedId] = $this->ownSpeciesWithBreed($this->clinic, 'Hurón');
        $patient = $this->patientOf($this->clientIn($this->clinic), ['species_id' => $speciesId, 'breed_id' => $breedId]);
        DB::table('species')->where('id', $speciesId)->update(['is_active' => false]);
        DB::table('breeds')->where('id', $breedId)->update(['is_active' => false]);

        $this->putJson("/api/patients/{$patient->id}", ['name' => 'Bandido', 'species_id' => $speciesId, 'breed_id' => $breedId])
            ->assertOk()
            ->assertJsonPath('data.name', 'Bandido')
            ->assertJsonPath('data.species.id', $speciesId)
            ->assertJsonPath('data.breed.id', $breedId);
    }

    public function test_choosing_another_inactive_species_or_breed_is_rejected(): void
    {
        [$speciesId, $breedId] = $this->ownSpeciesWithBreed($this->clinic, 'Hurón');
        [$inactiveSpeciesId, $inactiveBreedId] = $this->ownSpeciesWithBreed($this->clinic, 'Erizo', active: false);
        $patient = $this->patientOf($this->clientIn($this->clinic), ['species_id' => $speciesId, 'breed_id' => $breedId]);

        $this->patchJson("/api/patients/{$patient->id}", ['species_id' => $inactiveSpeciesId, 'breed_id' => null])
            ->assertUnprocessable()
            ->assertJsonPath('errors.species_id.0', 'La especie seleccionada no existe o está inactiva.');

        // Otra raza inactiva de su misma especie.
        $otherBreedId = DB::table('breeds')->insertGetId([
            'clinic_id' => $this->clinic->id, 'species_id' => $speciesId, 'name' => 'Hurón angora',
            'is_active' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->patchJson("/api/patients/{$patient->id}", ['breed_id' => $otherBreedId])
            ->assertUnprocessable()
            ->assertJsonPath('errors.breed_id.0', 'La raza seleccionada no existe o está inactiva.');

        $this->patchJson("/api/patients/{$patient->id}", ['breed_id' => $inactiveBreedId])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['breed_id']);
    }

    /**
     * La excepción de la especie que el paciente ya tiene no deja pasar una de
     * otra clínica.
     */
    public function test_a_species_of_another_clinic_is_still_rejected(): void
    {
        [$foreignSpeciesId] = $this->ownSpeciesWithBreed($this->createClinic(), 'Hurón');
        $patient = $this->patientOf($this->clientIn($this->clinic));

        $this->patchJson("/api/patients/{$patient->id}", ['species_id' => $foreignSpeciesId])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['species_id']);
    }

    public function test_custom_fields_are_ignored_and_not_shown(): void
    {
        $patient = $this->patientOf($this->clientIn($this->clinic), ['custom_fields' => ['collar' => 'azul']]);

        $this->patchJson("/api/patients/{$patient->id}", ['custom_fields' => ['collar' => 'rojo']])
            ->assertOk()
            ->assertJsonMissingPath('data.custom_fields');

        $this->assertSame(['collar' => 'azul'], $patient->fresh()->custom_fields);
        $this->getJson("/api/patients/{$patient->id}")->assertOk()->assertJsonMissingPath('data.custom_fields');
    }

    public function test_the_microchip_is_unique_ignoring_the_patient_itself(): void
    {
        $client = $this->clientIn($this->clinic);
        $patient = $this->patientOf($client, ['microchip' => 'CHIP100']);
        $this->patientOf($client, ['microchip' => 'CHIP200']);

        $this->patchJson("/api/patients/{$patient->id}", ['microchip' => 'CHIP100'])->assertOk();
        $this->patchJson("/api/patients/{$patient->id}", ['microchip' => 'CHIP200'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['microchip']);
    }

    public function test_a_patient_of_another_clinic_is_not_found(): void
    {
        $foreign = $this->patientOf($this->clientIn($this->createClinic()));

        $this->patchJson("/api/patients/{$foreign->id}", ['name' => 'Otro'])->assertNotFound();
        $this->assertSame('Paciente', Patient::withoutGlobalScopes()->find($foreign->id)->name);
    }
}
