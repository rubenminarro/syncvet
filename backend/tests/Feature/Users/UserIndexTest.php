<?php

namespace Tests\Feature\Users;

use Tests\Concerns\InteractsWithUsers;
use Tests\TestCase;

/**
 * Listado de usuarios (FASE 2, entrega 2.2a): solo los de la clínica, con search,
 * role, branch_id y paginación.
 */
class UserIndexTest extends TestCase
{
    use InteractsWithUsers;

    public function test_the_list_shows_only_the_users_of_the_clinic_without_deleted_ones(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $this->userWithRoles($clinic, ['veterinario'], [$branch], ['first_name' => 'Ana', 'last_name' => 'Acosta']);
        $this->userWithRoles($clinic, ['recepcionista'], [], ['first_name' => 'Borrado', 'last_name' => 'Zárate'])->delete();
        $this->userWithRoles($this->createClinic(), ['recepcionista'], [], ['first_name' => 'Ajeno', 'last_name' => 'Aguilar']);
        $manager = $this->actAsUserManager($clinic);

        $response = $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('data.0.first_name', 'Ana')
            ->assertJsonPath('data.0.roles', [['name' => 'veterinario', 'label' => 'Veterinario']])
            ->assertJsonPath('data.0.branches.0.code', 'SUC-01')
            ->assertJsonMissingPath('data.0.password')
            ->assertJsonMissingPath('data.0.created_at');

        $this->assertContains($manager->id, collect($response->json('data'))->pluck('id'));
    }

    public function test_search_looks_in_first_name_last_name_and_email_literally(): void
    {
        $clinic = $this->createClinic();
        $this->userWithRoles($clinic, ['recepcionista'], [], ['first_name' => 'Rosa', 'last_name' => 'Villalba', 'email' => 'rv@example.com']);
        $this->userWithRoles($clinic, ['recepcionista'], [], ['first_name' => 'Pedro', 'last_name' => 'Rosales', 'email' => 'pedro_r@example.com']);
        $this->userWithRoles($clinic, ['recepcionista'], [], ['first_name' => 'Juan', 'last_name' => 'Duarte', 'email' => 'contacto.rosa@example.com']);
        $this->actAsUserManager($clinic);

        $this->getJson('/api/users?search=rosa')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/users?search=villalba')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.first_name', 'Rosa');
        $this->getJson('/api/users?search=contacto.')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.first_name', 'Juan');
        // "_" y "%" no son comodines (R-021).
        $this->getJson('/api/users?search=pedro_')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/users?search=r_v')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/users?search=%25')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_role_and_branch_filters(): void
    {
        $clinic = $this->createClinic();
        $default = $this->defaultBranchIn($clinic);
        $north = $this->branchIn($clinic, ['is_active' => false]);
        $this->userWithRoles($clinic, ['veterinario'], [$default], ['first_name' => 'Vera']);
        $this->userWithRoles($clinic, ['veterinario', 'recepcionista'], [$north], ['first_name' => 'Nora']);
        $this->userWithRoles($clinic, ['administrador'], [$default, $north], ['first_name' => 'Ada']);
        $this->actAsUserManager($clinic);

        $names = fn (string $query) => collect($this->getJson("/api/users?{$query}")->assertOk()->json('data'))->pluck('first_name')->sort()->values()->all();

        $this->assertSame(['Nora', 'Vera'], $names('role=veterinario'));
        $this->assertSame(['Ada'], $names('role=administrador'));
        // Una sucursal inactiva también filtra.
        $this->assertSame(['Ada', 'Nora'], $names("branch_id={$north->id}"));
        $this->assertSame(['Nora'], $names("branch_id={$north->id}&role=recepcionista"));
    }

    public function test_the_filters_are_validated_and_a_branch_of_another_clinic_is_rejected(): void
    {
        $clinic = $this->createClinic();
        $this->defaultBranchIn($clinic);
        $foreign = $this->defaultBranchIn($this->createClinic());
        $this->actAsUserManager($clinic);

        foreach (['role=superusuario', 'branch_id=x', "branch_id={$foreign->id}", 'per_page=0', 'per_page=101', 'page=0', 'search[]=x'] as $query) {
            $this->getJson("/api/users?{$query}")->assertUnprocessable();
        }
    }

    public function test_the_list_is_paginated(): void
    {
        $clinic = $this->createClinic();
        foreach (['Ana', 'Bea', 'Ceci', 'Dora'] as $name) {
            $this->userWithRoles($clinic, ['recepcionista'], [], ['first_name' => $name, 'last_name' => 'Aquino']);
        }
        $this->actAsUserManager($clinic);

        $this->getJson('/api/users?role=recepcionista&per_page=3&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.first_name', 'Dora')
            ->assertJsonPath('meta.pagination', ['total' => 4, 'per_page' => 3, 'current_page' => 2, 'last_page' => 2]);
    }
}
