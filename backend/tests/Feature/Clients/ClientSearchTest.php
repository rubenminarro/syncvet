<?php

namespace Tests\Feature\Clients;

use App\Enums\ClientKind;
use App\Models\Client;
use App\Models\Clinic;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Listado y búsqueda de clientes (CLAUDE.md §6.1; FASE 1, entrega 1.2), con el
 * rol recepcionista.
 */
class ClientSearchTest extends TestCase
{
    use InteractsWithAuth;

    private Clinic $clinic;

    /**
     * @var array<string, Client>
     */
    private array $clients;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = $this->createClinic();
        Sanctum::actingAs($this->userWithRole($this->clinic, 'recepcionista'));

        $this->clients = [
            'rosa' => $this->clientIn($this->clinic, [
                'first_name' => 'Rosa', 'last_name' => 'Benítez', 'document_number' => '99400010',
                'email' => 'rosa@example.com', 'phone' => '981000111',
            ]),
            'haras' => $this->clientIn($this->clinic, [
                'kind' => ClientKind::COMPANY, 'first_name' => null, 'last_name' => null, 'business_name' => 'Haras del Sur SA',
                'document_number' => '99999904-4', 'email' => 'contacto@example.com', 'phone' => '+595971222333',
            ]),
            'marta' => $this->clientIn($this->clinic, [
                'first_name' => 'Marta', 'last_name' => 'Ortiz', 'document_number' => '99400011',
                'email' => 'marta@example.com', 'phone' => '982444555',
            ]),
        ];

        $this->patientOf($this->clients['marta'], ['name' => 'Firulais', 'code' => 'PAC-000777']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function searches(): array
    {
        return [
            'nombre' => ['Rosa', 'rosa'],
            'apellido' => ['Benítez', 'rosa'],
            'razón social' => ['Haras', 'haras'],
            'documento' => ['99999904', 'haras'],
            'teléfono' => ['971222333', 'haras'],
            'email' => ['contacto@', 'haras'],
            'nombre de la mascota' => ['Firulais', 'marta'],
            'código de la mascota' => ['PAC-000777', 'marta'],
        ];
    }

    #[DataProvider('searches')]
    public function test_the_search_finds_the_client(string $search, string $expected): void
    {
        $this->getJson('/api/clients?search=' . urlencode($search))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->clients[$expected]->id);
    }

    public function test_a_search_without_matches_is_empty(): void
    {
        $this->getJson('/api/clients?search=Inexistente')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.pagination.total', 0);
    }

    public function test_the_kind_filter(): void
    {
        $this->getJson('/api/clients?kind=company')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->clients['haras']->id);

        $this->getJson('/api/clients?kind=person')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_an_invalid_kind_filter_is_rejected(): void
    {
        $this->getJson('/api/clients?kind=x')
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.kind.0', 'El tipo de cliente debe ser person (persona) o company (empresa).');
    }

    public function test_the_list_is_paginated(): void
    {
        $this->getJson('/api/clients?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.pagination', ['total' => 3, 'per_page' => 2, 'current_page' => 1, 'last_page' => 2]);

        $this->getJson('/api/clients?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.pagination.current_page', 2);
    }

    public function test_the_list_shows_the_display_name(): void
    {
        $response = $this->getJson('/api/clients')->assertOk();

        $this->assertEqualsCanonicalizing(
            ['Rosa Benítez', 'Haras del Sur SA', 'Marta Ortiz'],
            array_column($response->json('data'), 'display_name')
        );
    }

    /**
     * "%" y "_" son caracteres comunes, no comodines (R-021): "%" no devuelve todo y
     * "_" solo encuentra un email que lo tiene.
     */
    #[DataProvider('literalSearches')]
    public function test_wildcards_are_searched_literally(string $search, array $expected): void
    {
        $this->clientIn($this->clinic, [
            'first_name' => 'Ana', 'last_name' => 'Ríos', 'document_number' => '99400012',
            'email' => 'ana_rios@example.com', 'phone' => '983000111',
        ]);

        $response = $this->getJson('/api/clients?search=' . urlencode($search))->assertOk();

        $this->assertEqualsCanonicalizing($expected, array_column($response->json('data'), 'display_name'));
    }

    public static function literalSearches(): array
    {
        return [
            'porcentaje' => ['%', []],
            'guion bajo' => ['_', ['Ana Ríos']],
            'guion bajo en medio' => ['ana_r', ['Ana Ríos']],
            'barra invertida' => ['\\', []],
            'carácter de escape' => ['!', []],
        ];
    }
}
