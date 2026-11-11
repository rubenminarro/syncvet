<?php

namespace Tests\Feature\Clients;

use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Filtros de los listados de clientes y pacientes (FASE 1, entrega 1.2, revisión):
 * un filtro mal formado responde 422, nunca 500. Antes, per_page=abc, per_page=-3
 * y search[]=x llegaban a la consulta y daban 500, y per_page no tenía tope.
 */
class ListFiltersTest extends TestCase
{
    use InteractsWithAuth;

    protected function setUp(): void
    {
        parent::setUp();

        $clinic = $this->createClinic();
        Sanctum::actingAs($this->userWithRole($clinic, 'recepcionista'));
        $this->patientOf($this->clientIn($clinic));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function malformedFilters(): array
    {
        $cases = [
            'per_page no numérico' => ['per_page=abc', 'per_page'],
            'per_page negativo' => ['per_page=-3', 'per_page'],
            'per_page cero' => ['per_page=0', 'per_page'],
            'per_page sin tope' => ['per_page=100000', 'per_page'],
            'search como lista' => ['search[]=x', 'search'],
            'search de más de 100' => ['search=' . str_repeat('a', 101), 'search'],
            'page no numérica' => ['page=abc', 'page'],
            'page cero' => ['page=0', 'page'],
        ];

        $all = [];
        foreach (['clientes' => '/api/clients', 'pacientes' => '/api/patients'] as $list => $url) {
            foreach ($cases as $name => [$query, $field]) {
                $all["{$list}: {$name}"] = [$url, $query, $field];
            }
        }

        return $all;
    }

    #[DataProvider('malformedFilters')]
    public function test_a_malformed_filter_is_rejected(string $url, string $query, string $field): void
    {
        $this->getJson("{$url}?{$query}")
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['errors' => [$field]]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function lists(): array
    {
        return ['clientes' => ['/api/clients'], 'pacientes' => ['/api/patients']];
    }

    #[DataProvider('lists')]
    public function test_per_page_defaults_to_10_and_accepts_up_to_100(string $url): void
    {
        $this->getJson($url)->assertOk()->assertJsonPath('meta.pagination.perPage', 10);
        $this->getJson("{$url}?per_page=100&page=1&search=a")->assertOk()->assertJsonPath('meta.pagination.perPage', 100);
    }

    public function test_the_messages_name_the_field_in_spanish(): void
    {
        $this->getJson('/api/clients?per_page=0')
            ->assertJsonPath('errors.per_page.0', 'El campo resultados por página debe ser al menos 1.');
    }
}
