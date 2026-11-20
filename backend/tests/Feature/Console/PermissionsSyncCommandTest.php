<?php

namespace Tests\Feature\Console;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithUsers;
use Tests\TestCase;

/**
 * permissions:sync (FASE 4, entrega 4.1; pregunta 7 del diseño y ajuste de R-099): una base con
 * los permisos de la FASE 3 (sin consultations.show-clinical, con un permiso que ya salió y un rol
 * desordenado) queda igual a la foto de los roles (tests/Fixtures/clinic-roles.json); dos corridas
 * no cambian nada; los usuarios conservan sus roles.
 */
class PermissionsSyncCommandTest extends TestCase
{
    use InteractsWithUsers;

    private const ROLES_SNAPSHOT = __DIR__ . '/../../Fixtures/clinic-roles.json';

    /**
     * Los roles de la base, como la foto: cada uno con sus permisos en orden alfabético.
     *
     * @return array<string, list<string>>
     */
    private function roles(): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return Role::where('guard_name', 'api')->with('permissions')->orderBy('name')->get()
            ->mapWithKeys(fn (Role $role) => [$role->name => $role->permissions->pluck('name')->sort()->values()->all()])
            ->all();
    }

    /**
     * @return array<string, list<string>>
     */
    private function snapshot(): array
    {
        $snapshot = json_decode(file_get_contents(self::ROLES_SNAPSHOT), true, flags: JSON_THROW_ON_ERROR);
        ksort($snapshot);

        return $snapshot;
    }

    /**
     * La base como la dejó la FASE 3: sin el permiso nuevo, con uno que ya no está en PERMISSIONS y
     * con la recepción con un permiso de más.
     */
    private function phase3Database(): void
    {
        Permission::where('name', 'consultations.show-clinical')->delete();
        Permission::create(['name' => 'consultations.obsolete', 'guard_name' => 'api']);
        Role::findByName('recepcionista', 'api')->givePermissionTo('consultations.update');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_a_phase_3_database_ends_equal_to_the_snapshot(): void
    {
        $this->phase3Database();
        $this->assertNotSame($this->snapshot(), $this->roles());

        $this->artisan('permissions:sync')
            ->expectsOutputToContain('consultations.show-clinical')
            ->expectsOutputToContain('consultations.obsolete')
            ->expectsOutputToContain('Permisos y roles sincronizados.')
            ->assertSuccessful();

        $roles = $this->roles();
        $this->assertSame($this->snapshot(), $roles);
        $this->assertContains('consultations.show-clinical', $roles['administrador']);
        $this->assertContains('consultations.show-clinical', $roles['veterinario']);
        $this->assertNotContains('consultations.show-clinical', $roles['recepcionista']);
        $this->assertFalse(Permission::where('name', 'consultations.obsolete')->exists());
    }

    public function test_running_it_twice_changes_nothing(): void
    {
        $this->phase3Database();
        $this->artisan('permissions:sync')->assertSuccessful();
        $after = $this->roles();
        $permissions = Permission::orderBy('id')->pluck('name', 'id')->all();

        $this->artisan('permissions:sync')
            ->expectsOutputToContain('Los permisos y los roles ya están al día.')
            ->assertSuccessful();

        $this->assertSame($after, $this->roles());
        $this->assertSame($permissions, Permission::orderBy('id')->pluck('name', 'id')->all());
    }

    public function test_users_keep_their_roles(): void
    {
        $clinic = $this->createClinic();
        $users = [
            'administrador' => $this->userWithRoles($clinic, ['administrador']),
            'recepcionista' => $this->userWithRoles($clinic, ['recepcionista']),
            'veterinario' => $this->userWithRoles($clinic, ['veterinario', 'recepcionista']),
        ];
        $this->phase3Database();

        $this->artisan('permissions:sync')->assertSuccessful();

        $this->assertSame(['administrador'], $users['administrador']->fresh()->getRoleNames()->all());
        $this->assertSame(['recepcionista'], $users['recepcionista']->fresh()->getRoleNames()->all());
        $this->assertEqualsCanonicalizing(['veterinario', 'recepcionista'], $users['veterinario']->fresh()->getRoleNames()->all());
        $this->assertTrue($users['veterinario']->fresh()->can('consultations.show-clinical'));
        $this->assertFalse($users['recepcionista']->fresh()->can('consultations.show-clinical'));
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->phase3Database();
        $before = $this->roles();

        $this->artisan('permissions:sync', ['--dry-run' => true])
            ->expectsOutputToContain('Sin cambios (--dry-run).')
            ->assertSuccessful();

        $this->assertSame($before, $this->roles());
        $this->assertFalse(Permission::where('name', 'consultations.show-clinical')->exists());
    }
}
