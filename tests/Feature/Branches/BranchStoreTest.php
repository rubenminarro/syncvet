<?php

namespace Tests\Feature\Branches;

use App\Models\Branch;
use App\Models\Warehouse;
use App\Support\CurrentClinic;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithBranches;
use Tests\TestCase;

/**
 * Alta de una sucursal con su depósito por defecto (FASE 2, entrega 2.1).
 */
class BranchStoreTest extends TestCase
{
    use InteractsWithBranches;

    public function test_a_branch_is_created_with_its_default_warehouse(): void
    {
        $clinic = $this->createClinic();
        $default = $this->defaultBranchIn($clinic);
        $this->actAsBranchManager($clinic);

        $response = $this->postJson('/api/branches', $this->branchPayload(['code' => 'suc-02', 'warehouse_code' => ' dep-02 ']));

        $response->assertCreated()
            ->assertJsonPath('data.code', 'SUC-02')
            ->assertJsonPath('data.name', 'Sucursal San Lorenzo')
            ->assertJsonPath('data.establishment', '002')
            ->assertJsonPath('data.address', 'Avda. Mcal. López 1234')
            ->assertJsonPath('data.phone', '+595981200300')
            ->assertJsonPath('data.is_default', false)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonCount(1, 'data.warehouses')
            ->assertJsonPath('data.warehouses.0.code', 'DEP-02')
            ->assertJsonPath('data.warehouses.0.name', 'Principal')
            ->assertJsonPath('data.warehouses.0.is_default', true)
            ->assertJsonPath('data.warehouses.0.is_active', true);

        $id = $response->json('data.id');
        $this->assertSame('SUC-02', $this->branchRow($id)->code);
        $this->assertSame($clinic->id, (int) $this->branchRow($id)->clinic_id);
        $this->assertSame($id, (int) $this->warehouseRow($response->json('data.warehouses.0.id'))->branch_id);
        // La anterior sigue siendo la por defecto.
        $this->assertTrue((bool) $this->branchRow($default->id)->is_default);
        // Al usuario que la crea no se le asigna sola (diseño 2.1, alta de sucursal).
        $this->assertSame(0, DB::table('branch_user')->where('branch_id', $id)->count());
    }

    public function test_the_default_warehouse_takes_the_name_sent(): void
    {
        $clinic = $this->createClinic();
        $this->defaultBranchIn($clinic);
        $this->actAsBranchManager($clinic);

        $this->postJson('/api/branches', $this->branchPayload(['warehouse_name' => 'Farmacia']))
            ->assertCreated()
            ->assertJsonPath('data.warehouses.0.name', 'Farmacia');
    }

    public function test_is_default_moves_the_default_to_the_new_branch(): void
    {
        $clinic = $this->createClinic();
        $default = $this->defaultBranchIn($clinic);
        $this->actAsBranchManager($clinic);

        $id = $this->postJson('/api/branches', $this->branchPayload(['is_default' => true]))
            ->assertCreated()
            ->assertJsonPath('data.is_default', true)
            ->json('data.id');

        $this->assertTrue((bool) $this->branchRow($id)->is_default);
        $this->assertFalse((bool) $this->branchRow($default->id)->is_default);
    }

    public function test_the_first_branch_of_a_clinic_without_one_is_the_default(): void
    {
        $clinic = $this->createClinic();
        $this->actAsBranchManager($clinic);

        $this->postJson('/api/branches', $this->branchPayload())
            ->assertCreated()
            ->assertJsonPath('data.is_default', true);
    }

    public function test_the_code_is_unique_in_the_clinic_without_case(): void
    {
        $clinic = $this->createClinic();
        $this->defaultBranchIn($clinic);
        $this->actAsBranchManager($clinic);

        $response = $this->postJson('/api/branches', $this->branchPayload(['code' => 'suc-01']));

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonPath('errors.code.0', 'Ya hay una sucursal con ese código.');
        $this->assertSame(1, CurrentClinic::run($clinic, fn () => Branch::count()));
    }

    public function test_an_invalid_code_is_rejected(): void
    {
        $clinic = $this->createClinic();
        $this->actAsBranchManager($clinic);

        foreach (['SUC 02', 'SUCÚ-02', 'SUC_02', str_repeat('A', 21), ''] as $code) {
            $this->postJson('/api/branches', $this->branchPayload(['code' => $code]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('code');
        }
    }

    public function test_the_name_is_unique_without_case_accents_or_repeated_spaces(): void
    {
        $clinic = $this->createClinic();
        $this->defaultBranchIn($clinic);
        $this->actAsBranchManager($clinic);

        foreach (['CASA CENTRAL', 'Cása  Central', ' casa central '] as $name) {
            $this->postJson('/api/branches', $this->branchPayload(['name' => $name]))
                ->assertUnprocessable()
                ->assertJsonPath('errors.name.0', 'Ya hay una sucursal con ese nombre.');
        }
    }

    public function test_the_name_is_saved_without_repeated_spaces(): void
    {
        $clinic = $this->createClinic();
        $this->actAsBranchManager($clinic);

        $this->postJson('/api/branches', $this->branchPayload(['name' => '  Sucursal   Luque ']))
            ->assertCreated()
            ->assertJsonPath('data.name', 'Sucursal Luque');
    }

    public function test_the_establishment_has_three_digits_and_is_unique(): void
    {
        $clinic = $this->createClinic();
        $this->defaultBranchIn($clinic);
        $this->actAsBranchManager($clinic);

        foreach (['1', '0001', '000', 'abc', 2, ''] as $establishment) {
            $this->postJson('/api/branches', $this->branchPayload(['establishment' => $establishment]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('establishment');
        }

        $this->postJson('/api/branches', $this->branchPayload(['establishment' => '001']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.establishment.0', 'Ya hay una sucursal con ese código de establecimiento.');

        $this->postJson('/api/branches', $this->branchPayload(['establishment' => '999']))->assertCreated();
    }

    public function test_an_invalid_phone_is_rejected_and_both_contact_fields_are_optional(): void
    {
        $clinic = $this->createClinic();
        $this->actAsBranchManager($clinic);

        foreach (['12345', '0981123456', '+59598 1123'] as $phone) {
            $this->postJson('/api/branches', $this->branchPayload(['phone' => $phone]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('phone');
        }

        $this->postJson('/api/branches', $this->branchPayload(['phone' => null, 'address' => null]))
            ->assertCreated()
            ->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.address', null);
    }

    public function test_the_warehouse_code_is_required_and_unique_in_the_clinic(): void
    {
        $clinic = $this->createClinic();
        $this->defaultBranchIn($clinic);
        $this->actAsBranchManager($clinic);

        $this->postJson('/api/branches', $this->branchPayload(['warehouse_code' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('warehouse_code');

        $response = $this->postJson('/api/branches', $this->branchPayload(['warehouse_code' => 'dep-01']));

        $response->assertUnprocessable()->assertJsonPath('errors.warehouse_code.0', 'Ya hay un depósito con ese código.');
        $this->assertSame(1, CurrentClinic::run($clinic, fn () => Branch::count()));
        $this->assertSame(1, CurrentClinic::run($clinic, fn () => Warehouse::count()));
    }

    public function test_the_codes_and_establishment_of_another_clinic_are_free(): void
    {
        $other = $this->createClinic();
        $this->defaultBranchIn($other);
        $this->branchIn($other, ['code' => 'SUC-02', 'name' => 'Sucursal San Lorenzo', 'establishment' => '002'], 'DEP-02');

        $clinic = $this->createClinic();
        $this->defaultBranchIn($clinic);
        $this->actAsBranchManager($clinic);

        $this->postJson('/api/branches', $this->branchPayload())->assertCreated();
    }

    public function test_the_receptionist_lists_branches_but_does_not_create_them(): void
    {
        $clinic = $this->createClinic();
        $this->defaultBranchIn($clinic);
        $user = $this->userIn($clinic);
        $user->assignRole(Role::findByName('recepcionista', 'api'));
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/branches')->assertOk()->assertJsonCount(1, 'data');
        $this->assertErrorCode($this->postJson('/api/branches', $this->branchPayload()), 403, 'FORBIDDEN');
    }
}
