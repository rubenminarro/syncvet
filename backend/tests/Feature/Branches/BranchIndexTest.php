<?php

namespace Tests\Feature\Branches;

use Tests\Concerns\InteractsWithBranches;
use Tests\TestCase;

/**
 * Listado de sucursales (FASE 2, entrega 2.1).
 */
class BranchIndexTest extends TestCase
{
    use InteractsWithBranches;

    public function test_the_list_shows_the_active_branches_of_the_clinic_with_the_default_first(): void
    {
        $other = $this->createClinic();
        $this->defaultBranchIn($other);
        $clinic = $this->createClinic();
        $this->branchIn($clinic, ['code' => 'SUC-00', 'name' => 'Sucursal Luque', 'establishment' => '003']);
        $this->defaultBranchIn($clinic);
        $this->branchIn($clinic, ['code' => 'SUC-09', 'name' => 'Sucursal cerrada', 'establishment' => '009', 'is_active' => false]);
        $this->actAsBranchManager($clinic);

        $this->getJson('/api/branches')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'SUC-01')
            ->assertJsonPath('data.0.is_default', true)
            ->assertJsonPath('data.1.code', 'SUC-00')
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonMissingPath('data.0.warehouses');

        $this->getJson('/api/branches?include_inactive=1')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.2.is_active', false);
    }

    public function test_search_looks_in_code_and_name_literally(): void
    {
        $clinic = $this->createClinic();
        $this->defaultBranchIn($clinic);
        $this->branchIn($clinic, ['code' => 'LUQ-1', 'name' => 'Sucursal Luque', 'establishment' => '003']);
        $this->branchIn($clinic, ['code' => 'SL-100', 'name' => 'San Lorenzo', 'establishment' => '004']);
        $this->actAsBranchManager($clinic);

        $this->getJson('/api/branches?search=luq')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'LUQ-1');
        $this->getJson('/api/branches?search=central')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'SUC-01');
        $this->getJson('/api/branches?search=sl-1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'SL-100');
        // "%" y "_" no son comodines (R-021).
        $this->getJson('/api/branches?search=%25')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/branches?search=SUC_01')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_the_list_is_paginated_and_validates_its_filters(): void
    {
        $clinic = $this->createClinic();
        $this->defaultBranchIn($clinic);
        foreach (range(2, 4) as $n) {
            $this->branchIn($clinic, ['code' => "SUC-0{$n}", 'name' => "Sucursal {$n}", 'establishment' => "00{$n}"]);
        }
        $this->actAsBranchManager($clinic);

        $this->getJson('/api/branches?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.pagination', ['total' => 4, 'per_page' => 2, 'current_page' => 2, 'last_page' => 2]);

        foreach (['per_page=0', 'per_page=101', 'page=0', 'include_inactive=x', 'search[]=x'] as $query) {
            $this->getJson("/api/branches?{$query}")->assertUnprocessable();
        }
    }
}
