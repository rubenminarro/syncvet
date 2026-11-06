<?php

namespace Tests\Feature\Patients;

use App\Models\Clinic;
use App\Models\Patient;
use App\Support\CurrentClinic;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Alta de pacientes en clients/{client}/patients (CLAUDE.md §6.2; FASE 1,
 * entrega 1.3), con el rol recepcionista.
 */
class PatientStoreTest extends TestCase
{
    use InteractsWithAuth;

    private Clinic $clinic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = $this->createClinic();
        Sanctum::actingAs($this->userWithRole($this->clinic, 'recepcionista'));
    }

    public function test_the_owner_comes_from_the_url_and_not_from_the_body(): void
    {
        $owner = $this->clientIn($this->clinic);
        $other = $this->clientIn($this->clinic);

        $response = $this->store($owner->id, $this->patientPayload(['client_id' => $other->id]))
            ->assertCreated()
            ->assertJsonPath('data.client.id', $owner->id)
            ->assertJsonPath('data.name', 'Toby');

        $this->assertDatabaseHas('patients', ['id' => $response->json('data.id'), 'client_id' => $owner->id, 'clinic_id' => $this->clinic->id]);
    }

    public function test_the_owner_must_be_a_client_of_the_clinic(): void
    {
        $foreign = $this->clientIn($this->createClinic());

        $this->store($foreign->id, $this->patientPayload())->assertNotFound();
    }

    public function test_the_code_is_correlative_per_clinic(): void
    {
        $client = $this->clientIn($this->clinic);

        $this->store($client->id, $this->patientPayload())->assertCreated()->assertJsonPath('data.code', 'PAC-000001');
        $this->store($client->id, $this->patientPayload(['name' => 'Luna']))->assertCreated()->assertJsonPath('data.code', 'PAC-000002');

        $otherClinic = $this->createClinic();
        Sanctum::actingAs($this->userWithRole($otherClinic, 'recepcionista'));

        $this->store($this->clientIn($otherClinic)->id, $this->patientPayload())->assertCreated()->assertJsonPath('data.code', 'PAC-000001');
    }

    public function test_the_code_cannot_be_sent(): void
    {
        $client = $this->clientIn($this->clinic);

        $this->store($client->id, $this->patientPayload(['code' => 'PAC-999999']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'El código del paciente se asigna automáticamente.');

        $this->assertDatabaseMissing('patients', ['code' => 'PAC-999999']);
    }

    public function test_a_breed_of_another_species_is_rejected(): void
    {
        $client = $this->clientIn($this->clinic);

        $response = $this->store($client->id, $this->patientPayload(['breed_id' => $this->globalBreedId('Siamés', 'Felino')]));

        $this->assertErrorCode($response, 422, 'PATIENT_BREED_SPECIES_MISMATCH');
        $this->assertDatabaseCount('patients', 0);
    }

    public function test_a_breed_of_its_species_is_accepted(): void
    {
        $client = $this->clientIn($this->clinic);

        $this->store($client->id, $this->patientPayload(['breed_id' => $this->globalBreedId('Beagle')]))
            ->assertCreated()
            ->assertJsonPath('data.breed.name', 'Beagle');
    }

    public function test_an_own_active_species_and_breed_are_accepted(): void
    {
        [$speciesId, $breedId] = $this->ownSpeciesWithBreed($this->clinic, 'Hurón');

        $this->store($this->clientIn($this->clinic)->id, $this->patientPayload(['species_id' => $speciesId, 'breed_id' => $breedId]))
            ->assertCreated()
            ->assertJsonPath('data.species.id', $speciesId)
            ->assertJsonPath('data.breed.id', $breedId);
    }

    public function test_an_inactive_species_or_breed_is_rejected(): void
    {
        [$speciesId, $breedId] = $this->ownSpeciesWithBreed($this->clinic, 'Hurón', active: false);
        $client = $this->clientIn($this->clinic);

        $this->store($client->id, $this->patientPayload(['species_id' => $speciesId]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.species_id.0', 'La especie seleccionada no existe o está inactiva.');

        // La raza inactiva, con su especie activa.
        DB::table('species')->where('id', $speciesId)->update(['is_active' => true]);
        $this->store($client->id, $this->patientPayload(['species_id' => $speciesId, 'breed_id' => $breedId]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.breed_id.0', 'La raza seleccionada no existe o está inactiva.');

        $this->assertDatabaseCount('patients', 0);
    }

    public function test_a_species_of_another_clinic_is_rejected(): void
    {
        [$speciesId] = $this->ownSpeciesWithBreed($this->createClinic(), 'Hurón');

        $this->store($this->clientIn($this->clinic)->id, $this->patientPayload(['species_id' => $speciesId]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['species_id']);
    }

    #[DataProvider('invalidWeights')]
    public function test_an_invalid_weight_is_rejected(mixed $weight, string $message): void
    {
        $this->store($this->clientIn($this->clinic)->id, $this->patientPayload(['weight_kg' => $weight]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.weight_kg.0', $message);
    }

    public static function invalidWeights(): array
    {
        return [
            'más de 2 decimales' => [1.234, 'El peso admite hasta 2 decimales.'],
            'cero' => [0, 'El peso debe ser mayor que cero.'],
            'más de 2000' => [2000.01, 'El peso no puede superar los 2000 kg.'],
        ];
    }

    public function test_a_valid_weight_is_stored_with_two_decimals(): void
    {
        $this->store($this->clientIn($this->clinic)->id, $this->patientPayload(['weight_kg' => 12.5]))
            ->assertCreated()
            ->assertJsonPath('data.weight_kg', '12.50');
    }

    public function test_a_future_birth_date_is_rejected(): void
    {
        $this->store($this->clientIn($this->clinic)->id, $this->patientPayload(['birth_date' => now()->addDay()->format('Y-m-d')]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.birth_date.0', 'La fecha de nacimiento no puede ser futura.');
    }

    public function test_the_microchip_is_unique_in_the_clinic(): void
    {
        $client = $this->clientIn($this->clinic);
        $this->patientOf($client, ['microchip' => 'CHIP900']);

        $this->store($client->id, $this->patientPayload(['microchip' => 'CHIP900']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.microchip.0', 'Este microchip ya está registrado en otro paciente.');
    }

    public function test_a_microchip_longer_than_30_is_rejected(): void
    {
        $this->store($this->clientIn($this->clinic)->id, $this->patientPayload(['microchip' => str_repeat('A', 31)]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.microchip.0', 'El microchip no debe tener más de 30 caracteres.');
    }

    /**
     * custom_fields no forma parte de la API (R-015): se ignora y la respuesta no
     * lo muestra.
     */
    public function test_custom_fields_are_ignored_and_not_shown(): void
    {
        $response = $this->store($this->clientIn($this->clinic)->id, $this->patientPayload(['custom_fields' => ['collar' => 'rojo']]))
            ->assertCreated()
            ->assertJsonMissingPath('data.custom_fields');

        $patient = CurrentClinic::run($this->clinic, fn () => Patient::findOrFail($response->json('data.id')));
        $this->assertNull($patient->custom_fields);
    }

    /**
     * photo_path sigue en la respuesta hasta la entrega 1.5.
     */
    public function test_the_detail_still_shows_photo_path(): void
    {
        $this->store($this->clientIn($this->clinic)->id, $this->patientPayload())
            ->assertCreated()
            ->assertJsonStructure(['data' => ['photo_path']]);
    }

    private function store(int $clientId, array $payload): TestResponse
    {
        return $this->postJson("/api/clients/{$clientId}/patients", $payload);
    }
}
