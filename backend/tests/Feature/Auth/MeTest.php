<?php

namespace Tests\Feature\Auth;

use App\Models\Branch;
use App\Models\Veterinarian;
use App\Support\CurrentClinic;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithAuth;
use Tests\Concerns\InteractsWithUsers;
use Tests\TestCase;

/**
 * GET /api/me: usuario, clínica, sucursal actual, sucursales que puede elegir (entrega
 * front-00, B1), roles, permisos ordenados y ficha de veterinario o null.
 */
class MeTest extends TestCase
{
    use InteractsWithAuth;
    use InteractsWithUsers;

    public function test_me_returns_the_profile_with_sorted_permissions(): void
    {
        $clinic = $this->createClinic(['name' => 'Veterinaria Sur']);
        $branch = CurrentClinic::run($clinic, fn () => Branch::create([
            'code' => 'SUC-01', 'name' => 'Casa central', 'establishment' => '001', 'is_default' => true, 'is_active' => true,
        ]));
        $user = $this->userWithRole($clinic, 'recepcionista', ['current_branch_id' => $branch->id]);

        $response = $this->getWithToken($this->tokenFor($user), '/api/me');

        $expected = RolesAndPermissionsSeeder::clinicRoles()['recepcionista']['permissions'];
        sort($expected);

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.first_name', $user->first_name)
            ->assertJsonPath('data.last_name', $user->last_name)
            ->assertJsonPath('data.name', $user->name)
            ->assertJsonPath('data.clinic', ['id' => $clinic->id, 'name' => 'Veterinaria Sur', 'has_logo' => false])
            ->assertJsonPath('data.current_branch', ['id' => $branch->id, 'code' => 'SUC-01', 'name' => 'Casa central'])
            ->assertJsonPath('data.roles', ['recepcionista'])
            ->assertJsonPath('data.veterinarian', null);
        $this->assertSame($expected, $response->json('data.permissions'));
        $this->assertNotEmpty($expected);
    }

    public function test_me_returns_null_without_a_current_branch(): void
    {
        $user = $this->userWithRole($this->createClinic(), 'recepcionista');

        $this->getWithToken($this->tokenFor($user), '/api/me')->assertOk()->assertJsonPath('data.current_branch', null);
    }

    public function test_me_returns_the_veterinarian_record(): void
    {
        $clinic = $this->createClinic();
        $user = $this->userWithRole($clinic, 'veterinario');
        $vet = CurrentClinic::run($clinic, fn () => Veterinarian::create(['user_id' => $user->id, 'code' => 'VET-000001']));

        $this->getWithToken($this->tokenFor($user), '/api/me')
            ->assertOk()
            ->assertJsonPath('data.veterinarian', ['id' => $vet->id, 'code' => 'VET-000001']);
    }

    /**
     * El administrador pasa todo por Gate::before: /me le muestra todos los
     * permisos aunque a su rol le falte alguno.
     */
    public function test_administrator_receives_every_permission(): void
    {
        Role::findByName('administrador', 'api')->revokePermissionTo('clients.index');
        $user = $this->userWithRole($this->createClinic(), 'administrador');

        $expected = RolesAndPermissionsSeeder::PERMISSIONS;
        sort($expected);

        $permissions = $this->getWithToken($this->tokenFor($user), '/api/me')->assertOk()->json('data.permissions');

        $this->assertSame($expected, $permissions);
    }

    /**
     * B1: branches trae las sucursales activas asignadas al usuario, por code; ni las
     * inactivas asignadas ni las activas sin asignar.
     */
    public function test_me_returns_the_active_assigned_branches_ordered_by_code(): void
    {
        $clinic = $this->createClinic();
        $third = $this->branchIn($clinic, ['code' => 'SUC-03', 'name' => 'Sucursal Sur']);
        $first = $this->defaultBranchIn($clinic);
        $inactive = $this->branchIn($clinic, ['code' => 'SUC-02', 'name' => 'Sucursal Este']);
        $this->branchIn($clinic, ['code' => 'SUC-00', 'name' => 'Sin asignar']);
        $user = $this->userWithRoles($clinic, ['recepcionista'], [$third, $inactive, $first]);
        DB::table('branches')->where('id', $inactive->id)->update(['is_active' => false]);

        $this->getWithToken($this->tokenFor($user), '/api/me')
            ->assertOk()
            ->assertJsonPath('data.branches', [
                ['id' => $first->id, 'code' => 'SUC-01', 'name' => 'Casa central'],
                ['id' => $third->id, 'code' => 'SUC-03', 'name' => 'Sucursal Sur'],
            ]);
    }

    public function test_me_returns_no_branches_without_assigned_ones(): void
    {
        $user = $this->userWithRole($this->createClinic(), 'recepcionista');

        $this->getWithToken($this->tokenFor($user), '/api/me')->assertOk()->assertJsonPath('data.branches', []);
    }

    /**
     * Pregunta 1 del diseño: el administrador también ve solo las suyas, porque
     * me/current-branch le exige la asignación igual que a los demás.
     */
    public function test_the_administrator_only_sees_the_assigned_branches(): void
    {
        $clinic = $this->createClinic();
        $default = $this->defaultBranchIn($clinic);
        $this->branchIn($clinic, ['code' => 'SUC-02']);
        $admin = $this->userWithRoles($clinic, ['administrador'], [$default]);

        $this->getWithToken($this->tokenFor($admin), '/api/me')
            ->assertOk()
            ->assertJsonPath('data.branches', [['id' => $default->id, 'code' => 'SUC-01', 'name' => 'Casa central']]);
    }

    /**
     * Fallo cerrado: una sucursal de otra clínica asignada a mano en branch_user no aparece.
     */
    public function test_a_branch_of_another_clinic_assigned_by_hand_is_not_listed(): void
    {
        $clinic = $this->createClinic();
        $default = $this->defaultBranchIn($clinic);
        $foreign = $this->defaultBranchIn($this->createClinic());
        $user = $this->userWithRoles($clinic, ['recepcionista'], [$default]);
        DB::table('branch_user')->insert(['branch_id' => $foreign->id, 'user_id' => $user->id]);

        $this->getWithToken($this->tokenFor($user), '/api/me')
            ->assertOk()
            ->assertJsonPath('data.branches', [['id' => $default->id, 'code' => 'SUC-01', 'name' => 'Casa central']]);
    }

    public function test_login_returns_the_same_branches(): void
    {
        $clinic = $this->createClinic();
        $default = $this->defaultBranchIn($clinic);
        $north = $this->branchIn($clinic, ['code' => 'SUC-02', 'name' => 'Sucursal Norte']);
        $user = $this->userWithRoles($clinic, ['recepcionista'], [$north, $default]);

        $this->login($user->email)
            ->assertOk()
            ->assertJsonPath('data.user.branches', [
                ['id' => $default->id, 'code' => 'SUC-01', 'name' => 'Casa central'],
                ['id' => $north->id, 'code' => 'SUC-02', 'name' => 'Sucursal Norte'],
            ]);
    }

    /**
     * Una sola consulta para las sucursales, sin N+1: con una o con cuatro, GET me hace
     * las mismas consultas.
     */
    public function test_the_branches_are_loaded_in_a_single_query(): void
    {
        $clinic = $this->createClinic();
        $branches = [$this->defaultBranchIn($clinic)];
        $one = $this->userWithRoles($clinic, ['recepcionista'], $branches);
        foreach (['SUC-02', 'SUC-03', 'SUC-04'] as $code) {
            $branches[] = $this->branchIn($clinic, ['code' => $code]);
        }
        $four = $this->userWithRoles($clinic, ['recepcionista'], $branches);

        [$queriesOfOne, $branchQueriesOfOne] = $this->meQueries($this->tokenFor($one), 1);
        [$queriesOfFour, $branchQueriesOfFour] = $this->meQueries($this->tokenFor($four), 4);

        $this->assertSame($queriesOfOne, $queriesOfFour);
        $this->assertSame(1, $branchQueriesOfOne);
        $this->assertSame(1, $branchQueriesOfFour);
    }

    /**
     * @return array{0: int, 1: int} consultas de GET me y, de ellas, las de branch_user
     */
    private function meQueries(string $token, int $expectedBranches): array
    {
        $this->forgetAuthenticatedUser();
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = Str::lower($query->sql);
        });

        $this->getWithToken($token, '/api/me')->assertOk()->assertJsonCount($expectedBranches, 'data.branches');
        $sql = $queries;
        $queries = [];

        return [count($sql), count(array_filter($sql, fn (string $q) => str_contains($q, 'branch_user')))];
    }
}
