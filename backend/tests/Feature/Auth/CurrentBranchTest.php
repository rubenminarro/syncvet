<?php

namespace Tests\Feature\Auth;

use App\Http\Requests\UpdateCurrentBranchRequest;
use App\Models\User;
use App\Services\AuthService;
use App\Support\CurrentClinic;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithUsers;
use Tests\TestCase;

/**
 * PATCH me/current-branch (FASE 2, entrega 2.2b): cualquier usuario autenticado elige
 * su sucursal actual entre las activas que tiene asignadas. Responde MeResource.
 */
class CurrentBranchTest extends TestCase
{
    use InteractsWithUsers;

    public function test_an_assigned_active_branch_becomes_the_current_one(): void
    {
        $clinic = $this->createClinic();
        $default = $this->defaultBranchIn($clinic);
        $north = $this->branchIn($clinic, ['code' => 'SUC-02', 'name' => 'Sucursal Norte']);
        $user = $this->userWithRoles($clinic, ['recepcionista'], [$default, $north], ['current_branch_id' => $default->id]);
        $token = $user->createToken('api')->plainTextToken;

        $this->withRealToken($token)->patchJson('/api/me/current-branch', ['branch_id' => $north->id])
            ->assertOk()
            ->assertJsonPath('message', 'Sucursal actual actualizada.')
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.current_branch', ['id' => $north->id, 'code' => 'SUC-02', 'name' => 'Sucursal Norte'])
            ->assertJsonPath('data.branches', [
                ['id' => $default->id, 'code' => 'SUC-01', 'name' => 'Casa central'],
                ['id' => $north->id, 'code' => 'SUC-02', 'name' => 'Sucursal Norte'],
            ])
            ->assertJsonPath('data.roles', ['recepcionista']);

        $this->assertSame($north->id, $user->fresh()->current_branch_id);
        $this->withRealToken($token)->getJson('/api/me')->assertJsonPath('data.current_branch.id', $north->id);
    }

    public function test_a_branch_not_assigned_inactive_or_of_another_clinic_is_rejected(): void
    {
        $clinic = $this->createClinic();
        $default = $this->defaultBranchIn($clinic);
        $unassigned = $this->branchIn($clinic, ['code' => 'SUC-02']);
        $inactive = $this->branchIn($clinic, ['code' => 'SUC-03']);
        $foreign = $this->defaultBranchIn($this->createClinic());
        $user = $this->userWithRoles($clinic, ['veterinario'], [$default, $inactive], ['current_branch_id' => $default->id]);
        DB::table('branches')->where('id', $inactive->id)->update(['is_active' => false]);
        $token = $user->createToken('api')->plainTextToken;

        foreach ([$unassigned->id, $inactive->id, $foreign->id, 999999, 'x', null] as $branchId) {
            $response = $this->withRealToken($token)->patchJson('/api/me/current-branch', ['branch_id' => $branchId]);

            $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
            $response->assertJsonValidationErrors('branch_id');
        }

        $this->withRealToken($token)->patchJson('/api/me/current-branch', ['branch_id' => $unassigned->id])
            ->assertJsonPath('errors.branch_id.0', 'La sucursal no existe, está inactiva o no está asignada a tu usuario.');
        $this->assertSame($default->id, $user->fresh()->current_branch_id);
    }

    /**
     * El Service la vuelve a revisar con la clínica bloqueada: una sucursal inactivada
     * después de la validación → 422 en branch_id.
     */
    public function test_a_branch_deactivated_after_the_validation_is_rejected(): void
    {
        $clinic = $this->createClinic();
        $default = $this->defaultBranchIn($clinic);
        $north = $this->branchIn($clinic, ['code' => 'SUC-02']);
        $user = $this->userWithRoles($clinic, ['recepcionista'], [$default, $north], ['current_branch_id' => $default->id]);
        DB::table('branches')->where('id', $north->id)->update(['is_active' => false]);

        try {
            CurrentClinic::run($clinic, fn () => app(AuthService::class)->setCurrentBranch($user, $north->id));
            $this->fail('Aceptó una sucursal inactiva.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('branch_id', $exception->errors());
        }

        $this->assertSame($default->id, User::findOrFail($user->id)->current_branch_id);
    }

    /**
     * El Service también revisa la asignación, sin el Request: una sucursal activa de la
     * clínica que el usuario no tiene asignada → 422 en branch_id.
     */
    public function test_a_branch_not_assigned_is_rejected_by_the_service(): void
    {
        $clinic = $this->createClinic();
        $default = $this->defaultBranchIn($clinic);
        $unassigned = $this->branchIn($clinic, ['code' => 'SUC-02']);
        $user = $this->userWithRoles($clinic, ['recepcionista'], [$default], ['current_branch_id' => $default->id]);

        try {
            CurrentClinic::run($clinic, fn () => app(AuthService::class)->setCurrentBranch($user, $unassigned->id));
            $this->fail('Aceptó una sucursal que el usuario no tiene asignada.');
        } catch (ValidationException $exception) {
            $this->assertSame(['branch_id' => [UpdateCurrentBranchRequest::BRANCH_NOT_AVAILABLE]], $exception->errors());
        }

        $this->assertSame($default->id, User::findOrFail($user->id)->current_branch_id);
    }

    public function test_it_needs_a_token(): void
    {
        $this->assertErrorCode($this->patchJson('/api/me/current-branch', ['branch_id' => 1]), 401, 'UNAUTHENTICATED');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function roles(): array
    {
        return ['recepcionista' => ['recepcionista'], 'administrador' => ['administrador']];
    }

    /**
     * Entrega front-00, B1: me.branches son exactamente las que acepta me/current-branch.
     * Cada una responde 200, y cualquier otra sucursal (de la clínica o de otra) responde 422.
     * También el administrador (revisión, H4): con una sucursal inactiva asignada, ni la ve ni
     * la puede elegir, aunque Gate::before le dé todos los permisos.
     */
    #[DataProvider('roles')]
    public function test_me_branches_are_exactly_the_ones_accepted_as_current(string $role): void
    {
        $clinic = $this->createClinic();
        $default = $this->defaultBranchIn($clinic);
        $north = $this->branchIn($clinic, ['code' => 'SUC-02']);
        $inactive = $this->branchIn($clinic, ['code' => 'SUC-03']);
        $this->branchIn($clinic, ['code' => 'SUC-04']);
        $foreign = $this->defaultBranchIn($this->createClinic());
        $user = $this->userWithRoles($clinic, [$role], [$default, $north, $inactive]);
        DB::table('branch_user')->insert(['branch_id' => $foreign->id, 'user_id' => $user->id]);
        DB::table('branches')->where('id', $inactive->id)->update(['is_active' => false]);
        $token = $user->createToken('api')->plainTextToken;

        $listed = array_column($this->withRealToken($token)->getJson('/api/me')->assertOk()->json('data.branches'), 'id');
        $this->assertSame([$default->id, $north->id], $listed);

        foreach ($listed as $branchId) {
            $this->withRealToken($token)->patchJson('/api/me/current-branch', ['branch_id' => $branchId])
                ->assertOk()
                ->assertJsonPath('data.current_branch.id', $branchId);
        }

        $others = DB::table('branches')->whereNotIn('id', $listed)->pluck('id')->all();
        $this->assertCount(3, $others);
        foreach ($others as $branchId) {
            $this->withRealToken($token)->patchJson('/api/me/current-branch', ['branch_id' => $branchId])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('branch_id');
        }
    }

    /**
     * Revisión, H8: el Request ya aplica Branch::selectableBy; una sucursal no elegible no llega
     * al Service (que la vuelve a revisar con la clínica bloqueada, como segunda barrera).
     */
    public function test_the_request_rejects_a_branch_that_is_not_selectable_before_the_service(): void
    {
        $clinic = $this->createClinic();
        $default = $this->defaultBranchIn($clinic);
        $unassigned = $this->branchIn($clinic, ['code' => 'SUC-02']);
        $inactive = $this->branchIn($clinic, ['code' => 'SUC-03']);
        $foreign = $this->defaultBranchIn($this->createClinic());
        $user = $this->userWithRoles($clinic, ['recepcionista'], [$default, $inactive]);
        DB::table('branch_user')->insert(['branch_id' => $foreign->id, 'user_id' => $user->id]);
        DB::table('branches')->where('id', $inactive->id)->update(['is_active' => false]);
        $token = $user->createToken('api')->plainTextToken;

        $this->mock(AuthService::class, fn ($mock) => $mock->shouldNotReceive('setCurrentBranch'));

        foreach ([$unassigned->id, $inactive->id, $foreign->id] as $branchId) {
            $this->withRealToken($token)->patchJson('/api/me/current-branch', ['branch_id' => $branchId])
                ->assertUnprocessable()
                ->assertJsonPath('errors.branch_id', [UpdateCurrentBranchRequest::BRANCH_NOT_AVAILABLE]);
        }
    }
}
