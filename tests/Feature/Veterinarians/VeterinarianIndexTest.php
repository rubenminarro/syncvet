<?php

namespace Tests\Feature\Veterinarians;

use Tests\Concerns\InteractsWithVeterinarians;
use Tests\TestCase;

/**
 * Listado de veterinarios (FASE 2, entrega 2.3): los activos por defecto; search en
 * código, nombre del usuario y matrícula; status; branch_id por las sucursales del
 * usuario (P5). La recepción lo lista (permiso existente).
 */
class VeterinarianIndexTest extends TestCase
{
    use InteractsWithVeterinarians;

    public function test_lists_the_active_veterinarians_of_the_clinic_with_pagination(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $this->veterinarianOf($this->veterinarianUser($clinic, [$branch]));
        $this->veterinarianOf($this->veterinarianUser($clinic));
        $this->veterinarianOf($this->veterinarianUser($clinic), ['status' => 'inactive']);
        $this->veterinarianOf($this->veterinarianUser($this->createClinic()));
        $deleted = $this->veterinarianOf($this->veterinarianUser($clinic));
        $this->actAsVeterinarianManager($clinic);
        $this->deleteJson("/api/veterinarians/{$deleted->id}")->assertOk();

        $this->getJson('/api/veterinarians?per_page=1')
            ->assertOk()
            ->assertJsonPath('message', 'Veterinarios obtenidos correctamente.')
            ->assertJsonPath('data.0.code', 'VET-000001')
            ->assertJsonPath('data.0.branches.0.code', 'SUC-01')
            ->assertJsonMissingPath('data.0.commission_percentage')
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('meta.pagination.perPage', 1)
            ->assertJsonPath('meta.pagination.lastPage', 2);

        $this->getJson('/api/veterinarians?status=inactive')
            ->assertOk()
            ->assertJsonPath('data.*.code', ['VET-000003']);
    }

    public function test_search_finds_by_code_user_name_and_license_number_literally(): void
    {
        $clinic = $this->createClinic();
        $this->veterinarianOf($this->veterinarianUser($clinic, [], ['first_name' => 'Marta', 'last_name' => 'Giménez', 'name' => 'Marta Giménez']), ['license_number' => 'MV-1001']);
        $this->veterinarianOf($this->veterinarianUser($clinic, [], ['first_name' => 'Hugo', 'last_name' => 'Benítez', 'name' => 'Hugo Benítez']), ['license_number' => 'MV_2002%']);
        $this->actAsVeterinarianManager($clinic);

        $this->getJson('/api/veterinarians?search=giménez')->assertOk()->assertJsonPath('data.*.code', ['VET-000001']);
        $this->getJson('/api/veterinarians?search=Hugo')->assertOk()->assertJsonPath('data.*.code', ['VET-000002']);
        $this->getJson('/api/veterinarians?search=VET-000002')->assertOk()->assertJsonPath('data.*.code', ['VET-000002']);
        $this->getJson('/api/veterinarians?search=1001')->assertOk()->assertJsonPath('data.*.code', ['VET-000001']);
        // "_" y "%" son literales (R-021).
        $this->getJson('/api/veterinarians?search=MV_')->assertOk()->assertJsonPath('data.*.code', ['VET-000002']);
        $this->getJson('/api/veterinarians?search=%25')->assertOk()->assertJsonPath('data.*.code', ['VET-000002']);
    }

    public function test_branch_id_filters_by_the_branches_of_the_user(): void
    {
        $clinic = $this->createClinic();
        $default = $this->defaultBranchIn($clinic);
        $north = $this->branchIn($clinic, ['code' => 'SUC-02', 'is_active' => false]);
        $this->veterinarianOf($this->veterinarianUser($clinic, [$default]));
        $this->veterinarianOf($this->veterinarianUser($clinic, [$default, $north]));
        $foreignBranch = $this->defaultBranchIn($this->createClinic());
        $this->actAsVeterinarianManager($clinic);

        $this->getJson("/api/veterinarians?branch_id={$default->id}")->assertOk()->assertJsonPath('data.*.code', ['VET-000001', 'VET-000002']);
        // Una sucursal inactiva también filtra.
        $this->getJson("/api/veterinarians?branch_id={$north->id}")->assertOk()->assertJsonPath('data.*.code', ['VET-000002']);

        $this->getJson("/api/veterinarians?branch_id={$foreignBranch->id}")
            ->assertUnprocessable()
            ->assertJsonPath('errors.branch_id.0', 'La sucursal no existe.');
    }

    public function test_malformed_filters_answer_422(): void
    {
        $clinic = $this->createClinic();
        $this->actAsVeterinarianManager($clinic);

        $this->getJson('/api/veterinarians?status=retired&per_page=0&branch_id=x')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'per_page', 'branch_id']);
    }
}
