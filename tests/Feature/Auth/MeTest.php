<?php

namespace Tests\Feature\Auth;

use App\Models\Branch;
use App\Models\Veterinarian;
use App\Support\CurrentClinic;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * GET /api/me: usuario, clínica, sucursal actual, roles, permisos ordenados y
 * ficha de veterinario o null.
 */
class MeTest extends TestCase
{
    use InteractsWithAuth;

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
}
