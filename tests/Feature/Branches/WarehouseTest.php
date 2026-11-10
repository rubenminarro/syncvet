<?php

namespace Tests\Feature\Branches;

use App\Models\Warehouse;
use App\Support\CurrentClinic;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithBranches;
use Tests\TestCase;

/**
 * Depósitos de una sucursal (FASE 2, entrega 2.1): alta anidada, uno por defecto en
 * cada sucursal, edición plana sin cambio de sucursal.
 */
class WarehouseTest extends TestCase
{
    use InteractsWithBranches;

    public function test_a_warehouse_is_created_in_the_branch_of_the_url(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $other = $this->branchIn($clinic);
        $this->actAsBranchManager($clinic);

        $response = $this->postJson("/api/branches/{$branch->id}/warehouses", [
            'code' => 'dep-05',
            'name' => 'Farmacia',
            'branch_id' => $other->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.branch_id', $branch->id)
            ->assertJsonPath('data.code', 'DEP-05')
            ->assertJsonPath('data.name', 'Farmacia')
            ->assertJsonPath('data.is_default', false)
            ->assertJsonPath('data.is_active', true);

        $this->assertSame($branch->id, (int) $this->warehouseRow($response->json('data.id'))->branch_id);
    }

    public function test_is_default_moves_the_default_only_inside_the_branch(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $other = $this->branchIn($clinic);
        $this->actAsBranchManager($clinic);

        $id = $this->postJson("/api/branches/{$branch->id}/warehouses", ['code' => 'DEP-05', 'name' => 'Farmacia', 'is_default' => true])
            ->assertCreated()
            ->assertJsonPath('data.is_default', true)
            ->json('data.id');

        $this->assertSame([$id], DB::table('warehouses')->where('branch_id', $branch->id)->where('is_default', true)->pluck('id')->all());
        // El por defecto de la otra sucursal no cambia.
        $this->assertSame(1, DB::table('warehouses')->where('branch_id', $other->id)->where('is_default', true)->count());

        // Y por la edición, de vuelta al primero.
        $first = DB::table('warehouses')->where('branch_id', $branch->id)->where('code', 'DEP-01')->value('id');
        $this->patchJson("/api/warehouses/{$first}", ['is_default' => true])->assertOk()->assertJsonPath('data.is_default', true);
        $this->assertSame([$first], DB::table('warehouses')->where('branch_id', $branch->id)->where('is_default', true)->pluck('id')->all());
        $this->assertSame(1, DB::table('warehouses')->where('branch_id', $other->id)->where('is_default', true)->count());
    }

    public function test_the_default_warehouse_cannot_be_deactivated_nor_unmarked(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $default = DB::table('warehouses')->where('branch_id', $branch->id)->value('id');
        $this->actAsBranchManager($clinic);

        $response = $this->patchJson("/api/warehouses/{$default}", ['is_active' => false]);
        $this->assertErrorCode($response, 409, 'WAREHOUSE_DEFAULT_NOT_DEACTIVATABLE');
        $response->assertJsonPath('message', 'El depósito por defecto no se puede inactivar: marcá otro como depósito por defecto primero.');

        $this->patchJson("/api/warehouses/{$default}", ['is_default' => false])
            ->assertUnprocessable()
            ->assertJsonPath('errors.is_default.0', 'Siempre tiene que haber un depósito por defecto en la sucursal: marcá otro.');

        $this->assertTrue((bool) $this->warehouseRow($default)->is_active);
        $this->assertTrue((bool) $this->warehouseRow($default)->is_default);
    }

    public function test_another_warehouse_is_deactivated_but_an_inactive_one_is_not_the_default(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $warehouse = $this->warehouseIn($branch);
        $this->actAsBranchManager($clinic);

        $this->patchJson("/api/warehouses/{$warehouse->id}", ['is_active' => false, 'is_default' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->patchJson("/api/warehouses/{$warehouse->id}", ['is_default' => true])
            ->assertUnprocessable()
            ->assertJsonPath('errors.is_default.0', 'Un depósito inactivo no puede ser el depósito por defecto: activalo primero.');
    }

    public function test_a_warehouse_does_not_change_its_branch(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $other = $this->branchIn($clinic);
        $warehouse = $this->warehouseIn($branch);
        $this->actAsBranchManager($clinic);

        $this->patchJson("/api/warehouses/{$warehouse->id}", ['branch_id' => $other->id, 'name' => 'Otro'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.branch_id.0', 'Un depósito no cambia de sucursal: creá uno nuevo en la otra sucursal.');

        $this->assertSame($branch->id, (int) $this->warehouseRow($warehouse->id)->branch_id);
    }

    public function test_the_code_is_unique_in_the_clinic_and_the_name_inside_the_branch(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $other = $this->branchIn($clinic);
        $this->actAsBranchManager($clinic);

        // DEP-01 es de la sucursal por defecto: el código es único en toda la clínica.
        $this->postJson("/api/branches/{$other->id}/warehouses", ['code' => 'dep-01', 'name' => 'Farmacia'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Ya hay un depósito con ese código.');

        // "Principal" ya está en la sucursal, sin importar mayúsculas ni acentos.
        $this->postJson("/api/branches/{$branch->id}/warehouses", ['code' => 'DEP-05', 'name' => 'PRÍNCIPAL'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Ya hay un depósito con ese nombre en la sucursal.');

        // En otra sucursal, el mismo nombre es libre.
        $this->postJson("/api/branches/{$other->id}/warehouses", ['code' => 'DEP-05', 'name' => 'Farmacia'])->assertCreated();
        $this->postJson("/api/branches/{$branch->id}/warehouses", ['code' => 'DEP-06', 'name' => 'Farmacia'])->assertCreated();

        // Al editar, los suyos se aceptan.
        $id = DB::table('warehouses')->where('code', 'DEP-06')->value('id');
        $this->patchJson("/api/warehouses/{$id}", ['code' => 'dep-06', 'name' => 'farmacia'])->assertOk();
        $this->patchJson("/api/warehouses/{$id}", ['code' => 'DEP-05'])->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_the_list_shows_the_active_warehouses_of_the_branch(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $this->warehouseIn($branch, ['code' => 'DEP-08', 'name' => 'Farmacia']);
        $this->warehouseIn($branch, ['code' => 'DEP-09', 'name' => 'Baja', 'is_active' => false]);
        $this->branchIn($clinic);
        $this->actAsBranchManager($clinic);

        $this->getJson("/api/branches/{$branch->id}/warehouses")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'DEP-01')
            ->assertJsonPath('meta.pagination.total', 2);

        $this->getJson("/api/branches/{$branch->id}/warehouses?include_inactive=1")->assertOk()->assertJsonCount(3, 'data');
        $this->getJson("/api/branches/{$branch->id}/warehouses?search=farm")->assertOk()->assertJsonCount(1, 'data');
        // search también busca en el código.
        $this->getJson("/api/branches/{$branch->id}/warehouses?search=dep-08")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'DEP-08');
        $this->getJson("/api/branches/{$branch->id}/warehouses?include_inactive=x")->assertUnprocessable();
    }

    /**
     * Una sucursal inactiva no suma depósitos (desvío 7 de la implementación, rechazado
     * por el usuario): 422 en branch. Reactivada, sí.
     */
    public function test_a_warehouse_is_not_created_in_an_inactive_branch(): void
    {
        $clinic = $this->createClinic();
        $this->defaultBranchIn($clinic);
        $branch = $this->branchIn($clinic, ['is_active' => false]);
        $this->actAsBranchManager($clinic);

        $response = $this->postJson("/api/branches/{$branch->id}/warehouses", ['code' => 'DEP-05', 'name' => 'Farmacia']);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonPath('errors.branch.0', 'La sucursal está inactiva.');
        $this->assertSame(1, DB::table('warehouses')->where('branch_id', $branch->id)->count());

        $this->patchJson("/api/branches/{$branch->id}", ['is_active' => true])->assertOk();

        $this->postJson("/api/branches/{$branch->id}/warehouses", ['code' => 'DEP-05', 'name' => 'Farmacia'])
            ->assertCreated()
            ->assertJsonPath('data.branch_id', $branch->id);
        $this->assertSame(2, DB::table('warehouses')->where('branch_id', $branch->id)->count());
    }

    public function test_a_warehouse_or_branch_of_another_clinic_is_not_found(): void
    {
        $foreign = $this->defaultBranchIn($this->createClinic());
        $foreignWarehouse = CurrentClinic::run($foreign->clinic, fn () => Warehouse::first());
        $clinic = $this->createClinic();
        $this->defaultBranchIn($clinic);
        $this->actAsBranchManager($clinic);

        $this->assertErrorCode($this->patchJson("/api/warehouses/{$foreignWarehouse->id}", ['name' => 'Ajeno']), 404, 'RESOURCE_NOT_FOUND');
        $this->assertErrorCode($this->getJson("/api/branches/{$foreign->id}/warehouses"), 404, 'RESOURCE_NOT_FOUND');
        $this->assertErrorCode($this->postJson("/api/branches/{$foreign->id}/warehouses", ['code' => 'DEP-05', 'name' => 'Ajeno']), 404, 'RESOURCE_NOT_FOUND');

        $this->assertSame('Principal', $this->warehouseRow($foreignWarehouse->id)->name);
        $this->assertSame(1, DB::table('warehouses')->where('branch_id', $foreign->id)->count());
    }
}
