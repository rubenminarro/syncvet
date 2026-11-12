<?php

namespace Tests\Feature\Patients;

use App\Enums\ClientKind;
use App\Models\Client;
use App\Models\Clinic;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Listado y búsqueda de pacientes (CLAUDE.md §6.2; FASE 1, entrega 1.3), con el
 * rol recepcionista.
 */
class PatientSearchTest extends TestCase
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

        $this->marta = $this->clientIn($this->clinic, ['first_name' => 'Marta', 'last_name' => 'Benítez', 'document_number' => '99400101', 'phone' => '981777001']);
        $this->empresa = $this->clientIn($this->clinic, [
            'kind' => ClientKind::COMPANY, 'first_name' => null, 'last_name' => null,
            'business_name' => 'Granja Los Robles', 'document_number' => '99400102', 'phone' => '981777002',
        ]);

        $this->patientOf($this->marta, ['name' => 'Firulais', 'code' => 'PAC-000501', 'microchip' => 'CHIPA1']);
        $this->patientOf($this->empresa, ['name' => 'Manchas', 'code' => 'PAC-000502', 'species_id' => $this->globalSpeciesId('Felino')]);
    }

    #[DataProvider('searches')]
    public function test_search_finds_the_patient(string $search, string $expected): void
    {
        $this->getJson('/api/patients?search=' . urlencode($search))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', $expected);
    }

    public static function searches(): array
    {
        return [
            'nombre' => ['Firu', 'Firulais'],
            'código' => ['PAC-000502', 'Manchas'],
            'microchip' => ['CHIPA1', 'Firulais'],
            'nombre del dueño' => ['Marta', 'Firulais'],
            'apellido del dueño' => ['Benítez', 'Firulais'],
            'razón social del dueño' => ['Robles', 'Manchas'],
            'documento del dueño' => ['99400102', 'Manchas'],
            'teléfono del dueño' => ['981777001', 'Firulais'],
        ];
    }

    public function test_search_without_matches_is_empty(): void
    {
        $this->getJson('/api/patients?search=Inexistente')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_filter_by_species(): void
    {
        $this->getJson('/api/patients?species_id=' . $this->globalSpeciesId('Felino'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Manchas');
    }

    public function test_an_invalid_species_filter_is_rejected(): void
    {
        $this->getJson('/api/patients?species_id=perro')->assertUnprocessable()->assertJsonValidationErrors(['species_id']);
    }

    public function test_the_list_is_paginated(): void
    {
        $this->getJson('/api/patients?per_page=1&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Manchas')
            ->assertJsonPath('meta.pagination', ['total' => 2, 'per_page' => 1, 'current_page' => 2, 'last_page' => 2]);
    }

    public function test_the_list_only_shows_patients_of_the_clinic(): void
    {
        $this->patientOf($this->clientIn($this->createClinic()), ['name' => 'Ajeno']);

        $this->getJson('/api/patients?search=Ajeno')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/patients')->assertOk()->assertJsonPath('meta.pagination.total', 2);
    }

    public function test_the_nested_list_only_shows_patients_of_that_client(): void
    {
        $this->patientOf($this->marta, ['name' => 'Bobby']);

        $this->getJson("/api/clients/{$this->marta->id}/patients")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Bobby')
            ->assertJsonPath('data.1.name', 'Firulais');
    }

    /**
     * "%", "_" y la barra invertida son caracteres comunes, no comodines (R-021):
     * ninguno devuelve todo el listado.
     */
    #[DataProvider('literalSearches')]
    public function test_wildcards_are_searched_literally(string $search): void
    {
        $this->getJson('/api/patients?search=' . urlencode($search))
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 0);
    }

    public static function literalSearches(): array
    {
        return [
            'porcentaje' => ['%'],
            'guion bajo' => ['_'],
            'barra invertida' => ['\\'],
            'carácter de escape' => ['!'],
            'patrón' => ['F%s'],
        ];
    }
}
