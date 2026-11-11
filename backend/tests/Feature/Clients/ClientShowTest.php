<?php

namespace Tests\Feature\Clients;

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Detalle de un cliente (CLAUDE.md §6.1; FASE 1, entrega 1.2), con el rol
 * recepcionista.
 */
class ClientShowTest extends TestCase
{
    use InteractsWithAuth;

    public function test_the_detail_brings_its_patients_with_species_and_breed(): void
    {
        $clinic = $this->createClinic();
        $client = $this->clientIn($clinic);
        $speciesId = $this->globalSpeciesId('Canino');
        $breedId = DB::table('breeds')->where('species_id', $speciesId)->where('name', 'Beagle')->value('id');
        $withBreed = $this->patientOf($client, ['name' => 'Toby', 'breed_id' => $breedId]);
        $withoutBreed = $this->patientOf($client, ['name' => 'Michi', 'species_id' => $this->globalSpeciesId('Felino')]);
        Sanctum::actingAs($this->userWithRole($clinic, 'recepcionista'));

        $response = $this->getJson("/api/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $client->id)
            ->assertJsonPath('data.display_name', 'Ana Gómez')
            ->assertJsonCount(2, 'data.patients');

        $patients = collect($response->json('data.patients'))->keyBy('id');

        $this->assertSame(['id' => $speciesId, 'name' => 'Canino'], $patients[$withBreed->id]['species']);
        $this->assertSame(['id' => $breedId, 'name' => 'Beagle'], $patients[$withBreed->id]['breed']);
        $this->assertSame('Felino', $patients[$withoutBreed->id]['species']['name']);
        $this->assertNull($patients[$withoutBreed->id]['breed']);
    }

    public function test_a_client_without_patients_has_an_empty_list(): void
    {
        $clinic = $this->createClinic();
        $client = $this->clientIn($clinic);
        Sanctum::actingAs($this->userWithRole($clinic, 'recepcionista'));

        $this->getJson("/api/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.patients', []);
    }
}
