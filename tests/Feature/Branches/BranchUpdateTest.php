<?php

namespace Tests\Feature\Branches;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithBranches;
use Tests\TestCase;

/**
 * Edición de una sucursal (FASE 2, entrega 2.1): la por defecto, la inactivación, el
 * establecimiento con facturas y la sucursal actual de los usuarios.
 */
class BranchUpdateTest extends TestCase
{
    use InteractsWithBranches;

    public function test_marking_another_branch_as_default_unmarks_the_previous_one(): void
    {
        $clinic = $this->createClinic();
        $default = $this->defaultBranchIn($clinic);
        $branch = $this->branchIn($clinic);
        $this->actAsBranchManager($clinic);

        $this->patchJson("/api/branches/{$branch->id}", ['is_default' => true])
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertTrue((bool) $this->branchRow($branch->id)->is_default);
        $this->assertFalse((bool) $this->branchRow($default->id)->is_default);
        $this->assertSame(1, DB::table('branches')->where('clinic_id', $clinic->id)->where('is_default', true)->count());
    }

    public function test_the_default_branch_cannot_stop_being_the_default(): void
    {
        $clinic = $this->createClinic();
        $default = $this->defaultBranchIn($clinic);
        $this->actAsBranchManager($clinic);

        $response = $this->patchJson("/api/branches/{$default->id}", ['is_default' => false]);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonPath('errors.is_default.0', 'Siempre tiene que haber una sucursal por defecto: marcá otra.');
        $this->assertTrue((bool) $this->branchRow($default->id)->is_default);
    }

    /**
     * El formulario manda todos los campos: is_default: false en una sucursal que no es
     * la por defecto no cambia nada.
     */
    public function test_is_default_false_on_another_branch_changes_nothing(): void
    {
        $clinic = $this->createClinic();
        $default = $this->defaultBranchIn($clinic);
        $branch = $this->branchIn($clinic);
        $this->actAsBranchManager($clinic);

        $this->patchJson("/api/branches/{$branch->id}", ['is_default' => false, 'name' => 'Sucursal Norte'])
            ->assertOk()
            ->assertJsonPath('data.is_default', false)
            ->assertJsonPath('data.name', 'Sucursal Norte');

        $this->assertTrue((bool) $this->branchRow($default->id)->is_default);
    }

    public function test_the_default_branch_cannot_be_deactivated(): void
    {
        $clinic = $this->createClinic();
        $default = $this->defaultBranchIn($clinic);
        $this->actAsBranchManager($clinic);

        $response = $this->patchJson("/api/branches/{$default->id}", ['is_active' => false]);

        $this->assertErrorCode($response, 409, 'BRANCH_DEFAULT_NOT_DEACTIVATABLE');
        $response->assertJsonPath('message', 'La sucursal por defecto no se puede inactivar: marcá otra como sucursal por defecto primero.');
        $this->assertTrue((bool) $this->branchRow($default->id)->is_active);
    }

    public function test_an_inactive_branch_cannot_be_the_default(): void
    {
        $clinic = $this->createClinic();
        $default = $this->defaultBranchIn($clinic);
        $inactive = $this->branchIn($clinic, ['is_active' => false]);
        $active = $this->branchIn($clinic);
        $this->actAsBranchManager($clinic);

        $this->patchJson("/api/branches/{$inactive->id}", ['is_default' => true])
            ->assertUnprocessable()
            ->assertJsonPath('errors.is_default.0', 'Una sucursal inactiva no puede ser la sucursal por defecto: activala primero.');

        // Tampoco inactivarla y marcarla en el mismo pedido.
        $this->patchJson("/api/branches/{$active->id}", ['is_default' => true, 'is_active' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_default');

        $this->assertTrue((bool) $this->branchRow($default->id)->is_default);

        // Activarla y marcarla en el mismo pedido, sí.
        $this->patchJson("/api/branches/{$inactive->id}", ['is_default' => true, 'is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.is_default', true)
            ->assertJsonPath('data.is_active', true);
    }

    public function test_another_branch_is_deactivated_and_reactivated(): void
    {
        $clinic = $this->createClinic();
        $this->defaultBranchIn($clinic);
        $branch = $this->branchIn($clinic);
        $this->actAsBranchManager($clinic);

        $this->patchJson("/api/branches/{$branch->id}", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        // Sus depósitos no cambian.
        $this->assertSame(1, DB::table('warehouses')->where('branch_id', $branch->id)->where('is_active', true)->count());

        $this->patchJson("/api/branches/{$branch->id}", ['is_active' => true])->assertOk()->assertJsonPath('data.is_active', true);
    }

    public function test_the_establishment_does_not_change_once_the_branch_has_invoices(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $this->invoiceInBranch($branch);
        $this->actAsBranchManager($clinic);

        $response = $this->patchJson("/api/branches/{$branch->id}", ['establishment' => '005']);

        $this->assertErrorCode($response, 422, 'BRANCH_ESTABLISHMENT_NOT_EDITABLE');
        $response->assertJsonPath('message', 'El código de establecimiento no se puede cambiar: la sucursal ya tiene facturas.');
        $this->assertSame('001', $this->branchRow($branch->id)->establishment);

        // El mismo valor (el formulario completo) y los demás campos, sí.
        $this->patchJson("/api/branches/{$branch->id}", ['establishment' => '001', 'name' => 'Casa matriz'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Casa matriz');
    }

    public function test_the_establishment_changes_without_invoices(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        // Una factura de otra sucursal no cuenta.
        $this->invoiceInBranch($this->branchIn($clinic));
        $this->actAsBranchManager($clinic);

        $this->patchJson("/api/branches/{$branch->id}", ['establishment' => '005'])
            ->assertOk()
            ->assertJsonPath('data.establishment', '005');
    }

    public function test_codes_name_and_establishment_stay_unique_and_its_own_are_accepted(): void
    {
        $clinic = $this->createClinic();
        $default = $this->defaultBranchIn($clinic);
        $branch = $this->branchIn($clinic, ['code' => 'SUC-02', 'name' => 'Sucursal Luque', 'establishment' => '002']);
        $this->actAsBranchManager($clinic);

        $this->patchJson("/api/branches/{$branch->id}", ['code' => 'suc-01', 'name' => 'CASA CENTRAL', 'establishment' => '001'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'name', 'establishment']);

        $this->patchJson("/api/branches/{$branch->id}", ['code' => 'suc-02', 'name' => 'sucursal luque', 'establishment' => '002'])
            ->assertOk()
            ->assertJsonPath('data.code', 'SUC-02')
            ->assertJsonPath('data.name', 'sucursal luque');

        $this->assertSame('SUC-01', $this->branchRow($default->id)->code);
    }

    /**
     * Pregunta 18: los usuarios con la sucursal inactivada como actual quedan sin
     * sucursal actual; su asignación (branch_user) se conserva.
     */
    public function test_users_lose_a_deactivated_current_branch_but_keep_the_assignment(): void
    {
        $clinic = $this->createClinic();
        $default = $this->defaultBranchIn($clinic);
        $branch = $this->branchIn($clinic);
        $inBranch = User::factory()->for($clinic)->create(['current_branch_id' => $branch->id]);
        $inBranch->branches()->attach([$branch->id, $default->id]);
        $inDefault = User::factory()->for($clinic)->create(['current_branch_id' => $default->id]);
        $this->actAsBranchManager($clinic);

        $this->patchJson("/api/branches/{$branch->id}", ['is_active' => false])->assertOk();

        $this->assertNull($inBranch->fresh()->current_branch_id);
        $this->assertSame($default->id, $inDefault->fresh()->current_branch_id);
        $this->assertSame(2, DB::table('branch_user')->where('user_id', $inBranch->id)->count());

        // Reactivarla no la devuelve como actual.
        $this->patchJson("/api/branches/{$branch->id}", ['is_active' => true])->assertOk();
        $this->assertNull($inBranch->fresh()->current_branch_id);
    }

    public static function verbs(): array
    {
        return ['PATCH' => ['patch'], 'PUT' => ['put']];
    }

    /**
     * PUT con la semántica parcial de PATCH (HttpVerbsTest): los campos que no llegan
     * no cambian.
     */
    #[DataProvider('verbs')]
    public function test_a_partial_update_keeps_the_fields_not_sent(string $verb): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        DB::table('branches')->where('id', $branch->id)->update(['address' => 'Palma 123', 'phone' => '+595981000111']);
        $this->actAsBranchManager($clinic);

        $this->json($verb, "/api/branches/{$branch->id}", ['name' => 'Casa matriz'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Casa matriz')
            ->assertJsonPath('data.code', 'SUC-01')
            ->assertJsonPath('data.establishment', '001')
            ->assertJsonPath('data.address', 'Palma 123')
            ->assertJsonPath('data.phone', '+595981000111')
            ->assertJsonPath('data.is_default', true)
            ->assertJsonCount(1, 'data.warehouses');
    }

    public function test_the_detail_shows_every_warehouse(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $this->warehouseIn($branch, ['code' => 'DEP-09', 'is_active' => false]);
        $this->actAsBranchManager($clinic);

        $this->getJson("/api/branches/{$branch->id}")
            ->assertOk()
            ->assertJsonPath('data.code', 'SUC-01')
            ->assertJsonCount(2, 'data.warehouses')
            ->assertJsonPath('data.warehouses.0.code', 'DEP-01')
            ->assertJsonPath('data.warehouses.1.is_active', false);
    }
}
