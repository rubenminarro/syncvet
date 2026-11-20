<?php

namespace App\Console\Commands;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Pone los permisos y los roles de una base que ya existe al día con RolesAndPermissionsSeeder
 * (FASE 4, entrega 4.1; pregunta 7 del diseño, R-099): los roles se siembran una vez y no se
 * vuelven a sincronizar solos. Es un comando y no una migración porque las migraciones no llaman
 * a modelos (§3).
 *
 * Idempotente, en una transacción: crea los permisos de PERMISSIONS que faltan (guard api), quita
 * los que salieron de PERMISSIONS, le da al administrador todos los permisos (además de
 * Gate::before) y sincroniza cada rol de clinicRoles() con su lista, como el seeder. Los roles son
 * de todas las clínicas (sin teams de Spatie). Los usuarios conservan sus roles: no se tocan.
 * Con --dry-run, solo informa.
 */
class PermissionsSyncCommand extends Command
{
    protected $signature = 'permissions:sync {--dry-run : Muestra los cambios, sin aplicarlos}';

    protected $description = 'Sincroniza los permisos y los roles de la base con RolesAndPermissionsSeeder';

    public function handle(PermissionRegistrar $registrar): int
    {
        $expected = RolesAndPermissionsSeeder::PERMISSIONS;
        $current = Permission::where('guard_name', 'api')->pluck('name')->all();
        $missing = array_values(array_diff($expected, $current));
        $obsolete = array_values(array_diff($current, $expected));
        $roles = $this->expectedRoles();
        $changes = $this->roleChanges($roles);

        $this->report($missing, $obsolete, $changes);

        if ($missing === [] && $obsolete === [] && $changes === []) {
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->components->info('Sin cambios (--dry-run).');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($missing, $obsolete, $roles) {
            foreach ($missing as $name) {
                Permission::create(['name' => $name, 'guard_name' => 'api']);
            }

            // delete() modelo por modelo: Spatie quita el permiso de los roles y de los usuarios.
            Permission::where('guard_name', 'api')->whereIn('name', $obsolete)->get()->each->delete();

            foreach ($roles as $name => $permissions) {
                Role::firstOrCreate(['name' => $name, 'guard_name' => 'api'])
                    ->syncPermissions(Permission::where('guard_name', 'api')->whereIn('name', $permissions)->get());
            }
        });

        $registrar->forgetCachedPermissions();

        $this->components->info('Permisos y roles sincronizados.');

        return self::SUCCESS;
    }

    /**
     * Cada rol con la lista que le corresponde: el administrador, todos; los demás, los de
     * clinicRoles().
     *
     * @return array<string, list<string>>
     */
    private function expectedRoles(): array
    {
        $roles = ['administrador' => RolesAndPermissionsSeeder::PERMISSIONS];

        foreach (RolesAndPermissionsSeeder::clinicRoles() as $name => $role) {
            $roles[$name] = array_values(array_intersect($role['permissions'], RolesAndPermissionsSeeder::PERMISSIONS));
        }

        return $roles;
    }

    /**
     * Lo que agrega y lo que quita a cada rol.
     *
     * @param array<string, list<string>> $roles
     * @return array<string, array{add: list<string>, remove: list<string>}>
     */
    private function roleChanges(array $roles): array
    {
        $changes = [];

        foreach ($roles as $name => $permissions) {
            $role = Role::where('name', $name)->where('guard_name', 'api')->first();
            $current = $role?->permissions->pluck('name')->all() ?? [];
            $add = array_values(array_diff($permissions, $current));
            $remove = array_values(array_diff($current, $permissions));

            if ($role === null || $add !== [] || $remove !== []) {
                $changes[$name] = ['add' => $add, 'remove' => $remove];
            }
        }

        return $changes;
    }

    /**
     * @param list<string> $missing
     * @param list<string> $obsolete
     * @param array<string, array{add: list<string>, remove: list<string>}> $changes
     */
    private function report(array $missing, array $obsolete, array $changes): void
    {
        if ($missing === [] && $obsolete === [] && $changes === []) {
            $this->components->info('Los permisos y los roles ya están al día.');

            return;
        }

        if ($missing !== []) {
            $this->components->twoColumnDetail('Permisos nuevos', implode(', ', $missing));
        }

        if ($obsolete !== []) {
            $this->components->twoColumnDetail('Permisos que se quitan', implode(', ', $obsolete));
        }

        foreach ($changes as $role => $change) {
            $detail = [];

            if ($change['add'] !== []) {
                $detail[] = '+ ' . implode(', ', $change['add']);
            }

            if ($change['remove'] !== []) {
                $detail[] = '- ' . implode(', ', $change['remove']);
            }

            $this->components->twoColumnDetail("Rol {$role}", $detail === [] ? 'nuevo' : implode(' ', $detail));
        }
    }
}
